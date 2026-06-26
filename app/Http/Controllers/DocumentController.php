<?php

namespace App\Http\Controllers;

use App\Exceptions\DocumentExportException;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\ApiDocument;
use App\Models\ApiDocumentLink;
use App\Models\ApiDocumentVersion;
use App\Models\Document;
use App\Models\Opportunity;
use App\Services\DocumentExportService;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->tenantQuery(Document::class);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('document_type', 'like', "%{$search}%");
            });
        }

        $documents = $query->with(['opportunity', 'contact'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('documents.index', compact('documents'));
    }

    public function create(): View
    {
        $opportunities = $this->tenantQuery(Opportunity::class)
            ->orderByDesc('created_at')
            ->get(['id', 'title']);

        return view('documents.create', compact('opportunities'));
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $opportunityId = $data['opportunity_id'] ?? null;

        // De-dupe: replace any existing document with the same file name already linked to the same opportunity.
        if ($opportunityId) {
            $existing = $this->tenantQuery(Document::class)
                ->where('opportunity_id', $opportunityId)
                ->where('file_name', $originalName)
                ->get();
            foreach ($existing as $dupe) {
                if ($dupe->file_path && Storage::disk('local')->exists($dupe->file_path)) {
                    Storage::disk('local')->delete($dupe->file_path);
                }
                $dupe->delete();
            }
        }

        $path = $file->store('documents', 'local');

        $document = Document::create($this->tenantData([
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'document_type' => $data['document_type'] ?? null,
            'opportunity_id' => $opportunityId,
            'contact_id'    => $data['contact_id'] ?? null,
            'file_path'     => $path,
            'file_name'     => $originalName,
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
        ]));

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Document uploaded successfully.');
    }

    public function show(Request $request, int $id): View
    {
        $document = $this->tenantQuery(Document::class)->findOrFail($id);

        $this->authorize('view', $document);

        return view('documents.show', compact('document'));
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        $document = $this->tenantQuery(Document::class)->findOrFail($id);

        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function view(Request $request, int $id): StreamedResponse
    {
        $document = $this->tenantQuery(Document::class)->findOrFail($id);

        $this->authorize('view', $document);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, $document->file_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    public function viewApiDoc(Request $request, int $id): StreamedResponse
    {
        $doc = $this->tenantQuery(ApiDocument::class)
            ->with('currentVersion')
            ->findOrFail($id);

        $ver = $doc->currentVersion;

        abort_unless($ver && $ver->storage_path && Storage::disk('local')->exists($ver->storage_path), 404);

        return Storage::disk('local')->response($ver->storage_path, $ver->original_filename, [
            'Content-Type' => $ver->mime_type,
        ]);
    }

    // =========================================================================
    // CRM-native content documents (inline rich text — managed in-app)
    // =========================================================================

    /** Convert + stream a content document in the requested format. */
    public function exportApiDoc(Request $request, int $id, string $format): Response
    {
        $doc = $this->tenantQuery(ApiDocument::class)->with('currentVersion')->findOrFail($id);
        $ver = $doc->currentVersion;

        abort_unless($ver, 404, 'No version available for this document.');

        try {
            $result = app(DocumentExportService::class)->export($ver, $format, $doc->name);
        } catch (DocumentExportException $e) {
            abort($e->status, $e->getMessage());
        }

        return response($result['body'], 200, [
            'Content-Type'        => $result['mime'],
            'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
        ]);
    }

    /** Create a content document directly from the CRM UI. */
    public function storeContentDoc(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:500',
            'content_body'   => 'required|string|max:5242880',
            'content_format' => ['nullable', Rule::in(ApiDocumentVersion::CONTENT_FORMATS)],
            'document_type'  => ['nullable', Rule::in(ApiDocument::DOCUMENT_TYPES)],
            'description'    => 'nullable|string|max:2000',
            'opportunity_id' => 'nullable|integer',
        ]);

        $doc = ApiDocument::create($this->tenantData([
            'name'           => $data['name'],
            'document_type'  => $data['document_type'] ?? 'report',
            'description'    => $data['description'] ?? null,
            'is_content_doc' => true,
            'is_sensitive'   => false,
        ]));

        $version = $this->makeContentVersion($doc, 1, $data);
        $doc->update(['current_version_id' => $version->id]);

        if (! empty($data['opportunity_id'])) {
            $opp = $this->tenantQuery(Opportunity::class)->find($data['opportunity_id']);
            if ($opp) {
                ApiDocumentLink::firstOrCreate([
                    'api_document_id' => $doc->id,
                    'entity_type'     => 'opportunity',
                    'entity_id'       => $opp->id,
                ]);
            }
        }

        return back()->with('success', 'Document created.');
    }

    /** Save an edit as a new version. */
    public function updateContentDoc(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'nullable|string|max:500',
            'content_body'   => 'required|string|max:5242880',
            'content_format' => ['nullable', Rule::in(ApiDocumentVersion::CONTENT_FORMATS)],
            'version_notes'  => 'nullable|string|max:2000',
        ]);

        $doc = $this->tenantQuery(ApiDocument::class)->findOrFail($id);

        $nextNum = (ApiDocumentVersion::where('api_document_id', $doc->id)->max('version_number') ?? 0) + 1;
        $version = $this->makeContentVersion($doc, $nextNum, $data);

        $patch = ['current_version_id' => $version->id, 'is_content_doc' => true];
        if (! empty($data['name'])) {
            $patch['name'] = $data['name'];
        }
        $doc->update($patch);

        return back()->with('success', "Saved as version {$nextNum}.");
    }

    /** Make an older version current again (non-destructive restore). */
    public function restoreContentVersion(Request $request, int $id, int $vid): RedirectResponse
    {
        $doc     = $this->tenantQuery(ApiDocument::class)->findOrFail($id);
        $version = ApiDocumentVersion::where('api_document_id', $doc->id)->where('id', $vid)->firstOrFail();

        $doc->update(['current_version_id' => $version->id]);

        return back()->with('success', "Restored version {$version->version_number}.");
    }

    /** Soft-delete a CRM-native (API) document from the UI. */
    public function destroyApiDoc(Request $request, int $id): RedirectResponse
    {
        $doc = $this->tenantQuery(ApiDocument::class)->findOrFail($id);
        $doc->delete();

        return back()->with('success', 'Document deleted.');
    }

    /** Build an inline-content version; sanitizes HTML, stores text + checksum. */
    private function makeContentVersion(ApiDocument $doc, int $versionNumber, array $data): ApiDocumentVersion
    {
        $format = $data['content_format'] ?? 'html';
        $body   = (string) $data['content_body'];

        if ($format === 'html') {
            $body = (string) RichText::sanitizeForStorage($body);
        }

        $ext  = $format === 'html' ? 'html' : ($format === 'markdown' ? 'md' : 'txt');
        $mime = ApiDocumentVersion::CONTENT_MIME_TYPES[$format] ?? 'text/plain';

        return ApiDocumentVersion::create([
            'api_document_id'   => $doc->id,
            'version_number'    => $versionNumber,
            'original_filename' => preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $doc->name) . '.' . $ext,
            'mime_type'         => $mime,
            'size_bytes'        => strlen($body),
            'checksum'          => hash('sha256', $body),
            'content_format'    => $format,
            'content_body'      => $body,
            'upload_source'     => 'inline_content',
            'version_notes'     => $data['version_notes'] ?? null,
        ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $document = $this->tenantQuery(Document::class)->findOrFail($id);

        $this->authorize('delete', $document);

        // Remove the physical file
        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('documents.index')
            ->with('success', 'Document deleted.');
    }
}

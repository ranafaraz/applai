<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessContactImportJob;
use App\Models\ContactImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactImportController extends Controller
{
    public function index(Request $request): View
    {
        $imports = $this->tenantQuery(ContactImport::class)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('imports.index', compact('imports'));
    }

    public function create(): View
    {
        return view('imports.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10 MB
        ]);

        $file = $request->file('csv_file');

        // Ensure the imports directory exists (Flysystem silently fails without it on some servers)
        $importDir = storage_path('app/private/imports');
        if (! is_dir($importDir)) {
            mkdir($importDir, 0775, true);
        }

        $path = $file->store('imports', 'local');

        if (! $path) {
            return back()->withErrors(['csv_file' => 'Failed to save the uploaded file. Please try again.']);
        }

        $fullPath = storage_path('app/private/' . $path);
        if (! file_exists($fullPath) || filesize($fullPath) === 0) {
            return back()->withErrors(['csv_file' => 'Uploaded file appears to be empty or could not be saved. Please try again.']);
        }

        $import = ContactImport::create($this->tenantData([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status'    => 'pending',
        ]));

        // Process synchronously — queue worker reliability is unverified; for
        // typical CSV sizes this completes in well under the request timeout.
        try {
            ProcessContactImportJob::dispatchSync($import);
        } catch (\Throwable) {
            // Service already set status=failed + error_message on the import record
        }

        return redirect()->route('imports.show', $import->id)
            ->with('success', 'Import processed. Check the results below.');
    }

    public function show(Request $request, int $id): View
    {
        $import = $this->tenantQuery(ContactImport::class)->findOrFail($id);
        $rows = $import->rows()->orderBy('row_number')->paginate(50);

        return view('imports.show', compact('import', 'rows'));
    }
}

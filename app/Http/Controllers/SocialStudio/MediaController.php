<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialActivityLog;
use App\Models\SocialMediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $user   = $request->user();
        $status = $request->input('status', 'all');

        $assets = SocialMediaAsset::where('user_id', $user->id)
            ->when($status !== 'all', fn ($q) => $q->where('approval_status', $status))
            ->orderByDesc('created_at')
            ->paginate(24);

        return view('social-studio.media.index', compact('assets', 'status'));
    }

    public function create(): View
    {
        return view('social-studio.media.create');
    }

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'file'         => 'required|file|mimes:jpg,jpeg,png,gif,webp|max:10240',
            'alt_text'     => 'required|string|max:500',
            'title'        => 'nullable|string|max:255',
            'rights_status'=> 'nullable|in:owned,licensed,generated,unknown',
        ]);

        $user = $request->user();
        $file = $request->file('file');

        $slug = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('social-media/' . $user->id, $slug, 'public');

        $asset = SocialMediaAsset::create([
            'tenant_id'              => $user->tenant_id,
            'user_id'                => $user->id,
            'storage_path'           => $path,
            'filename'               => $file->getClientOriginalName(),
            'mime_type'              => $file->getMimeType(),
            'size_bytes'             => $file->getSize(),
            'alt_text'               => $data['alt_text'],
            'caption_or_prompt_note' => $data['title'] ?? null,
            'rights_status'          => $data['rights_status'] ?? 'owned',
            'approval_status'        => 'approved',
        ]);

        SocialActivityLog::record(
            $user->id, $user->tenant_id,
            'media_uploaded', SocialMediaAsset::class, $asset->id,
            "Media uploaded: {$asset->filename}"
        );

        if ($request->expectsJson()) {
            return response()->json([
                'id'          => $asset->id,
                'label'       => ($asset->caption_or_prompt_note ?: $asset->filename) . ' (' . $asset->alt_text . ')',
                'storage_url' => Storage::disk('public')->url($asset->storage_path),
            ]);
        }

        return redirect()->route('social-studio.media.index')
            ->with('success', 'Media asset uploaded and approved.');
    }

    public function show(Request $request, int $id): View
    {
        $asset = SocialMediaAsset::where('user_id', $request->user()->id)->findOrFail($id);
        return view('social-studio.media.show', compact('asset'));
    }

    public function edit(Request $request, int $id): View
    {
        $asset = SocialMediaAsset::where('user_id', $request->user()->id)->findOrFail($id);
        return view('social-studio.media.edit', compact('asset'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'alt_text'     => 'required|string|max:500',
            'title'        => 'nullable|string|max:255',
            'rights_status'=> 'nullable|in:owned,licensed,generated,unknown',
            'source_notes' => 'nullable|string|max:1000',
        ]);

        $asset = SocialMediaAsset::where('user_id', $request->user()->id)->findOrFail($id);
        $asset->update($data);

        return redirect()->route('social-studio.media.index')
            ->with('success', 'Media asset updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $asset = SocialMediaAsset::where('user_id', $request->user()->id)->findOrFail($id);

        Storage::disk('public')->delete($asset->storage_path);
        $asset->delete();

        return redirect()->route('social-studio.media.index')
            ->with('success', 'Media asset deleted.');
    }
}

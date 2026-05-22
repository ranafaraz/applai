<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TagController extends Controller
{
    public function index(Request $request): View
    {
        $tags = Tag::where('user_id', $request->user()->id)
            ->withCount(['contacts', 'opportunities'])
            ->orderBy('name')
            ->paginate(50);

        return view('tags.index', compact('tags'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);

        $data['user_id'] = $request->user()->id;
        $data['slug']    = Str::slug($data['name']);

        $tag = Tag::firstOrCreate(
            ['user_id' => $data['user_id'], 'slug' => $data['slug']],
            $data
        );

        if ($request->wantsJson()) {
            return response()->json($tag, 201);
        }

        return redirect()->route('tags.index')->with('success', 'Tag created.');
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $tag = Tag::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
        ]);

        $data['slug'] = Str::slug($data['name']);
        $tag->update($data);

        if ($request->wantsJson()) {
            return response()->json($tag);
        }

        return redirect()->route('tags.index')->with('success', 'Tag updated.');
    }

    public function destroy(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $tag = Tag::where('user_id', $request->user()->id)->findOrFail($id);

        $tag->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('tags.index')->with('success', 'Tag deleted.');
    }

    /**
     * Attach a tag to a Contact or Opportunity.
     */
    public function attach(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tag_id'       => 'required|integer|exists:tags,id',
            'taggable_type' => 'required|in:contact,opportunity',
            'taggable_id'  => 'required|integer',
        ]);

        $tag = Tag::where('user_id', $request->user()->id)->findOrFail($data['tag_id']);

        $model = $this->resolveTaggable($data['taggable_type'], $data['taggable_id'], $request->user()->id);

        $model->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json(['success' => true]);
    }

    /**
     * Detach a tag from a Contact or Opportunity.
     */
    public function detach(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tag_id'        => 'required|integer|exists:tags,id',
            'taggable_type' => 'required|in:contact,opportunity',
            'taggable_id'   => 'required|integer',
        ]);

        $tag = Tag::where('user_id', $request->user()->id)->findOrFail($data['tag_id']);

        $model = $this->resolveTaggable($data['taggable_type'], $data['taggable_id'], $request->user()->id);

        $model->tags()->detach($tag->id);

        return response()->json(['success' => true]);
    }

    private function resolveTaggable(string $type, int $id, int $userId): Contact|Opportunity
    {
        return match ($type) {
            'contact'     => Contact::where('user_id', $userId)->findOrFail($id),
            'opportunity' => Opportunity::where('user_id', $userId)->findOrFail($id),
        };
    }
}

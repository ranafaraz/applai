<?php

namespace App\Http\Controllers\Api\Gpt\V1\Tag;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends GptController
{
    /** entity keyword => taggable model class (each owns user_id and a tags() morphToMany). */
    private const ENTITIES = [
        'contact'     => Contact::class,
        'opportunity' => Opportunity::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:200',
            'limit'  => 'nullable|integer|min:1|max:200',
        ]);

        $uid   = $this->apiUser($request)->id;
        $limit = min((int) $request->input('limit', 100), 200);

        $query = Tag::where('user_id', $uid)
            ->withCount(['contacts', 'opportunities']);

        if ($search = $request->input('search')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $tags = $query->orderBy('name')->limit($limit)->get();

        return $this->listResponse($tags->map(fn ($t) => $this->format($t))->values(), $limit);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'nullable|string|max:20',
            'slug'  => 'nullable|string|max:120',
        ]);

        $uid = $this->apiUser($request)->id;
        $slug = $data['slug'] ?? Str::slug($data['name']);

        // Find-or-create on (user_id, slug) so repeated creates are idempotent.
        $tag = Tag::firstOrCreate(
            ['user_id' => $uid, 'slug' => $slug],
            ['name' => $data['name'], 'color' => $data['color'] ?? '#6366f1'],
        );

        $this->audit($request, 'create_tag', 'tag', $tag->id, 'low',
            "name={$tag->name}", "id={$tag->id}");

        $tag->loadCount(['contacts', 'opportunities']);

        return response()->json(['data' => $this->format($tag)], $tag->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tag = Tag::where('user_id', $this->apiUser($request)->id)
            ->withCount(['contacts', 'opportunities'])
            ->findOrFail($id);

        return response()->json(['data' => $this->format($tag)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:20',
            'slug'  => 'sometimes|string|max:120',
        ]);

        $tag = Tag::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        foreach (['name', 'color', 'slug'] as $field) {
            if (array_key_exists($field, $data)) {
                $tag->{$field} = $data[$field];
            }
        }
        $tag->save();

        $this->audit($request, 'update_tag', 'tag', $tag->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$tag->id}");

        $tag->loadCount(['contacts', 'opportunities']);

        return response()->json(['data' => $this->format($tag)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tag = Tag::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        // Detach pivot rows before hard-deleting (no soft deletes on tags).
        $tag->contacts()->detach();
        $tag->opportunities()->detach();
        $tag->delete();

        $this->audit($request, 'delete_tag', 'tag', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /** Attach tags (by id and/or by name) to a contact or opportunity. */
    public function attach(Request $request): JsonResponse
    {
        $data = $this->validateAttachPayload($request);
        $uid  = $this->apiUser($request)->id;

        $entity  = $this->resolveEntity($data['entity'], $uid, $data['id']);
        $tagIds  = $this->resolveTagIds($uid, $data['tag_ids'] ?? [], $data['tags'] ?? []);

        if ($tagIds instanceof JsonResponse) {
            return $tagIds;
        }

        $entity->tags()->syncWithoutDetaching($tagIds);

        $this->audit($request, 'attach_tags', $data['entity'], (int) $data['id'], 'low',
            'tags=' . implode(',', $tagIds), 'count=' . count($tagIds));

        return response()->json([
            'entity'  => $data['entity'],
            'id'      => (int) $data['id'],
            'tags'    => $entity->tags()->orderBy('name')->get()->map(fn ($t) => $this->format($t)),
        ]);
    }

    /** Detach tags (by id) from a contact or opportunity. */
    public function detach(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity'    => 'required|string|in:' . implode(',', array_keys(self::ENTITIES)),
            'id'        => 'required|integer',
            'tag_ids'   => 'required|array|min:1',
            'tag_ids.*' => 'integer',
        ]);

        $uid    = $this->apiUser($request)->id;
        $entity = $this->resolveEntity($data['entity'], $uid, $data['id']);

        // Only detach tags the user actually owns.
        $owned = Tag::where('user_id', $uid)->whereIn('id', $data['tag_ids'])->pluck('id')->all();
        $entity->tags()->detach($owned);

        $this->audit($request, 'detach_tags', $data['entity'], (int) $data['id'], 'low',
            'tags=' . implode(',', $owned), 'count=' . count($owned));

        return response()->json([
            'entity' => $data['entity'],
            'id'     => (int) $data['id'],
            'tags'   => $entity->tags()->orderBy('name')->get()->map(fn ($t) => $this->format($t)),
        ]);
    }

    /** List the tags currently attached to a contact or opportunity. */
    public function on(Request $request, string $entity, int $id): JsonResponse
    {
        if (! array_key_exists($entity, self::ENTITIES)) {
            return response()->json(['error' => "Unsupported entity '{$entity}'. Allowed: " . implode(', ', array_keys(self::ENTITIES))], 422);
        }

        $uid   = $this->apiUser($request)->id;
        $model = $this->resolveEntity($entity, $uid, $id);
        $tags  = $model->tags()->orderBy('name')->get();

        return response()->json([
            'entity' => $entity,
            'id'     => $id,
            'tags'   => $tags->map(fn ($t) => $this->format($t)),
            'count'  => $tags->count(),
        ]);
    }

    private function validateAttachPayload(Request $request): array
    {
        $data = $request->validate([
            'entity'    => 'required|string|in:' . implode(',', array_keys(self::ENTITIES)),
            'id'        => 'required|integer',
            'tag_ids'   => 'nullable|array',
            'tag_ids.*' => 'integer',
            'tags'      => 'nullable|array',
            'tags.*'    => 'string|max:100',
        ]);

        if (empty($data['tag_ids']) && empty($data['tags'])) {
            abort(response()->json(['error' => 'Provide at least one of tag_ids or tags.'], 422));
        }

        return $data;
    }

    /** Resolve a user-owned taggable entity, or 404. */
    private function resolveEntity(string $entity, int $uid, int $id): Model
    {
        $class = self::ENTITIES[$entity];

        return $class::where('user_id', $uid)->findOrFail($id);
    }

    /**
     * Resolve a deduped list of owned tag ids from explicit ids + create-or-find
     * by name. Returns a 422 JsonResponse if any explicit tag_id is not owned.
     *
     * @return int[]|JsonResponse
     */
    private function resolveTagIds(int $uid, array $tagIds, array $names): array|JsonResponse
    {
        $ids = [];

        if (! empty($tagIds)) {
            $owned   = Tag::where('user_id', $uid)->whereIn('id', $tagIds)->pluck('id')->all();
            $missing = array_values(array_diff($tagIds, $owned));
            if (! empty($missing)) {
                return response()->json(['error' => 'Some tag_ids do not exist or are not owned by you.', 'missing_tag_ids' => $missing], 422);
            }
            $ids = array_merge($ids, $owned);
        }

        foreach ($names as $name) {
            $tag = Tag::firstOrCreate(
                ['user_id' => $uid, 'slug' => Str::slug($name)],
                ['name' => $name, 'color' => '#6366f1'],
            );
            $ids[] = $tag->id;
        }

        return array_values(array_unique($ids));
    }

    public function format(Tag $t): array
    {
        return [
            'id'                  => $t->id,
            'name'                => $t->name,
            'color'               => $t->color,
            'slug'                => $t->slug,
            'contacts_count'      => $t->contacts_count ?? null,
            'opportunities_count' => $t->opportunities_count ?? null,
            'created_at'          => $t->created_at?->toISOString(),
            'updated_at'          => $t->updated_at?->toISOString(),
        ];
    }
}

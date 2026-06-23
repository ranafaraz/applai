<?php

namespace App\Http\Controllers\Api\Gpt\V1\Content;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\ContentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentItemController extends GptController
{
    private const STATUSES = ['idea', 'draft', 'scheduled', 'published', 'archived'];

    /**
     * List content items with optional filters. Also powers the calendar view
     * when from/to are supplied (results are ordered by scheduled_for).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'       => 'nullable|string|in:' . implode(',', self::STATUSES),
            'content_type' => 'nullable|string|max:100',
            'channel'      => 'nullable|string|max:100',
            'from'         => 'nullable|date',
            'to'           => 'nullable|date',
            'search'       => 'nullable|string|max:200',
            'limit'        => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = ContentItem::where('user_id', $user->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->input('content_type')) {
            $query->where('content_type', $type);
        }
        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }
        if ($from = $request->input('from')) {
            $query->where('scheduled_for', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('scheduled_for', '<=', $to);
        }
        if ($search = $request->input('search')) {
            $query->where('title', 'like', '%' . $search . '%');
        }

        // Order unscheduled items last, scheduled ones chronologically.
        $items = $query
            ->orderByRaw('scheduled_for IS NULL')
            ->orderBy('scheduled_for')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $this->listResponse($items->map(fn ($i) => $this->format($i))->values(), $limit);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'required|string|max:500',
            'content_type'  => 'nullable|string|max:100',
            'channel'       => 'nullable|string|max:100',
            'status'        => 'nullable|in:' . implode(',', self::STATUSES),
            'body'          => 'nullable|string|max:100000',
            'notes'         => 'nullable|string|max:5000',
            'scheduled_for' => 'nullable|date',
            'meta'          => 'nullable|array',
        ]);

        $user = $this->apiUser($request);

        $item = ContentItem::create([
            'user_id'       => $user->id,
            'tenant_id'     => $user->tenant_id,
            'title'         => $data['title'],
            'content_type'  => $data['content_type'] ?? null,
            'channel'       => $data['channel'] ?? null,
            'status'        => $data['status'] ?? 'idea',
            'body'          => $data['body'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'scheduled_for' => $data['scheduled_for'] ?? null,
            'meta'          => $data['meta'] ?? null,
        ]);

        $this->audit($request, 'create_content_item', 'content_item', $item->id, 'low',
            "title={$item->title}, status={$item->status}", "id={$item->id}");

        return response()->json(['data' => $this->format($item)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $item = ContentItem::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        return response()->json(['data' => $this->format($item)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title'         => 'sometimes|string|max:500',
            'content_type'  => 'sometimes|nullable|string|max:100',
            'channel'       => 'sometimes|nullable|string|max:100',
            'status'        => 'sometimes|in:' . implode(',', self::STATUSES),
            'body'          => 'sometimes|nullable|string|max:100000',
            'notes'         => 'sometimes|nullable|string|max:5000',
            'scheduled_for' => 'sometimes|nullable|date',
            'meta'          => 'sometimes|nullable|array',
        ]);

        $user = $this->apiUser($request);
        $item = ContentItem::where('user_id', $user->id)->findOrFail($id);

        foreach (['title', 'content_type', 'channel', 'status', 'body', 'notes', 'scheduled_for', 'meta'] as $field) {
            if (array_key_exists($field, $data)) {
                $item->{$field} = $data[$field];
            }
        }

        $item->save();

        $this->audit($request, 'update_content_item', 'content_item', $item->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$item->id}");

        return response()->json(['data' => $this->format($item)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = ContentItem::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $item->delete();

        $this->audit($request, 'delete_content_item', 'content_item', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Mark a content item as published. Records the live URL and publish time.
     * Gated by the content:publish scope (separate from content:write).
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'published_url' => 'nullable|url|max:2000',
            'published_at'  => 'nullable|date',
        ]);

        $user = $this->apiUser($request);
        $item = ContentItem::where('user_id', $user->id)->findOrFail($id);

        $item->status        = 'published';
        $item->published_at  = $data['published_at'] ?? now();
        if (array_key_exists('published_url', $data)) {
            $item->published_url = $data['published_url'];
        }
        $item->save();

        $this->audit($request, 'publish_content_item', 'content_item', $item->id, 'medium',
            "id={$item->id}, url=" . ($item->published_url ?? 'null'), "published_at={$item->published_at}");

        return response()->json(['data' => $this->format($item)]);
    }

    public function format(ContentItem $i): array
    {
        return [
            'id'            => $i->id,
            'title'         => $i->title,
            'content_type'  => $i->content_type,
            'channel'       => $i->channel,
            'status'        => $i->status,
            'body'          => $i->body,
            'notes'         => $i->notes,
            'scheduled_for' => $i->scheduled_for?->toISOString(),
            'published_at'  => $i->published_at?->toISOString(),
            'published_url' => $i->published_url,
            'meta'          => $i->meta,
            'created_at'    => $i->created_at?->toISOString(),
            'updated_at'    => $i->updated_at?->toISOString(),
        ];
    }
}

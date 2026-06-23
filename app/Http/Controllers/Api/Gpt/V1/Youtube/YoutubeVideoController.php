<?php

namespace App\Http\Controllers\Api\Gpt\V1\Youtube;

use App\Http\Controllers\Api\Gpt\V1\GptController;
use App\Models\YoutubeVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YoutubeVideoController extends GptController
{
    private const STATUSES   = ['idea', 'scripting', 'recording', 'editing', 'scheduled', 'published', 'archived'];
    private const VISIBILITY  = ['public', 'unlisted', 'private'];

    /**
     * List videos with optional filters. Doubles as a production calendar when
     * from/to are supplied (results ordered by scheduled_for, unscheduled last).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'     => 'nullable|string|in:' . implode(',', self::STATUSES),
            'visibility' => 'nullable|string|in:' . implode(',', self::VISIBILITY),
            'channel'    => 'nullable|string|max:100',
            'video_id'   => 'nullable|string|max:64',
            'from'       => 'nullable|date',
            'to'         => 'nullable|date',
            'search'     => 'nullable|string|max:200',
            'limit'      => 'nullable|integer|min:1|max:100',
        ]);

        $user  = $this->apiUser($request);
        $limit = min((int) $request->input('limit', 50), 100);

        $query = YoutubeVideo::where('user_id', $user->id);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($visibility = $request->input('visibility')) {
            $query->where('visibility', $visibility);
        }
        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }
        if ($videoId = $request->input('video_id')) {
            $query->where('video_id', $videoId);
        }
        if ($from = $request->input('from')) {
            $query->where('scheduled_for', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('scheduled_for', '<=', $to);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $videos = $query
            ->orderByRaw('scheduled_for IS NULL')
            ->orderBy('scheduled_for')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data'  => $videos->map(fn ($v) => $this->format($v)),
            'count' => $videos->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'required|string|max:500',
            'video_id'         => 'nullable|string|max:64',
            'url'              => 'nullable|url|max:2000',
            'description'      => 'nullable|string|max:100000',
            'status'           => 'nullable|in:' . implode(',', self::STATUSES),
            'visibility'       => 'nullable|in:' . implode(',', self::VISIBILITY),
            'channel'          => 'nullable|string|max:100',
            'thumbnail_url'    => 'nullable|url|max:2000',
            'duration_seconds' => 'nullable|integer|min:0',
            'tags'             => 'nullable|array|max:50',
            'tags.*'           => 'string|max:100',
            'scheduled_for'    => 'nullable|date',
            'meta'             => 'nullable|array',
        ]);

        $user = $this->apiUser($request);

        $video = YoutubeVideo::create([
            'user_id'          => $user->id,
            'tenant_id'        => $user->tenant_id,
            'title'            => $data['title'],
            'video_id'         => $data['video_id'] ?? null,
            'url'              => $data['url'] ?? null,
            'description'      => $data['description'] ?? null,
            'status'           => $data['status'] ?? 'idea',
            'visibility'       => $data['visibility'] ?? 'public',
            'channel'          => $data['channel'] ?? null,
            'thumbnail_url'    => $data['thumbnail_url'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'tags'             => $data['tags'] ?? null,
            'scheduled_for'    => $data['scheduled_for'] ?? null,
            'meta'             => $data['meta'] ?? null,
        ]);

        $this->audit($request, 'create_youtube_video', 'youtube_video', $video->id, 'low',
            "title={$video->title}, status={$video->status}", "id={$video->id}");

        return response()->json(['data' => $this->format($video)], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $video = YoutubeVideo::where('user_id', $this->apiUser($request)->id)->findOrFail($id);

        return response()->json(['data' => $this->format($video)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title'            => 'sometimes|string|max:500',
            'video_id'         => 'sometimes|nullable|string|max:64',
            'url'              => 'sometimes|nullable|url|max:2000',
            'description'      => 'sometimes|nullable|string|max:100000',
            'status'           => 'sometimes|in:' . implode(',', self::STATUSES),
            'visibility'       => 'sometimes|in:' . implode(',', self::VISIBILITY),
            'channel'          => 'sometimes|nullable|string|max:100',
            'thumbnail_url'    => 'sometimes|nullable|url|max:2000',
            'duration_seconds' => 'sometimes|nullable|integer|min:0',
            'tags'             => 'sometimes|nullable|array|max:50',
            'tags.*'           => 'string|max:100',
            'view_count'       => 'sometimes|integer|min:0',
            'like_count'       => 'sometimes|integer|min:0',
            'comment_count'    => 'sometimes|integer|min:0',
            'scheduled_for'    => 'sometimes|nullable|date',
            'meta'             => 'sometimes|nullable|array',
        ]);

        $user  = $this->apiUser($request);
        $video = YoutubeVideo::where('user_id', $user->id)->findOrFail($id);

        if (empty($data)) {
            return response()->json(['error' => 'No updatable fields provided.'], 422);
        }

        foreach (array_keys($data) as $field) {
            $video->{$field} = $data[$field];
        }

        $video->save();

        $this->audit($request, 'update_youtube_video', 'youtube_video', $video->id, 'low',
            'fields=' . implode(',', array_keys($data)), "id={$video->id}");

        return response()->json(['data' => $this->format($video)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $video = YoutubeVideo::where('user_id', $this->apiUser($request)->id)->findOrFail($id);
        $video->delete();

        $this->audit($request, 'delete_youtube_video', 'youtube_video', $id, 'low', "id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Mark a video as published. Records the live URL/video_id and publish time.
     * This only updates CRM state — it does not upload or publish on YouTube.
     */
    public function publish(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'video_id'     => 'nullable|string|max:64',
            'url'          => 'nullable|url|max:2000',
            'published_at' => 'nullable|date',
        ]);

        $user  = $this->apiUser($request);
        $video = YoutubeVideo::where('user_id', $user->id)->findOrFail($id);

        $video->status       = 'published';
        $video->published_at = $data['published_at'] ?? now();
        if (array_key_exists('video_id', $data)) {
            $video->video_id = $data['video_id'];
        }
        if (array_key_exists('url', $data)) {
            $video->url = $data['url'];
        }
        $video->save();

        $this->audit($request, 'publish_youtube_video', 'youtube_video', $video->id, 'medium',
            "id={$video->id}, video_id=" . ($video->video_id ?? 'null'), "published_at={$video->published_at}");

        return response()->json(['data' => $this->format($video)]);
    }

    public function format(YoutubeVideo $v): array
    {
        return [
            'id'               => $v->id,
            'title'            => $v->title,
            'video_id'         => $v->video_id,
            'url'              => $v->url,
            'description'      => $v->description,
            'status'           => $v->status,
            'visibility'       => $v->visibility,
            'channel'          => $v->channel,
            'thumbnail_url'    => $v->thumbnail_url,
            'duration_seconds' => $v->duration_seconds,
            'tags'             => $v->tags,
            'view_count'       => $v->view_count,
            'like_count'       => $v->like_count,
            'comment_count'    => $v->comment_count,
            'scheduled_for'    => $v->scheduled_for?->toISOString(),
            'published_at'     => $v->published_at?->toISOString(),
            'meta'             => $v->meta,
            'created_at'       => $v->created_at?->toISOString(),
            'updated_at'       => $v->updated_at?->toISOString(),
        ];
    }
}

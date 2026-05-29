<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\ApiAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttachmentController extends GptController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filename'   => 'required|string|max:500',
            'public_url' => 'required|string|max:2048',
            'mime_type'  => ['required', 'string', Rule::in(ApiAttachment::ALLOWED_MIME_TYPES)],
            'size_bytes' => 'required|integer|min:1|max:' . ApiAttachment::MAX_SIZE_BYTES,
            'category'   => ['nullable', Rule::in(ApiAttachment::CATEGORIES)],
            'notes'      => 'nullable|string|max:2000',
        ]);

        // Validate the URL is publicly reachable (scheme + no private IPs)
        $urlError = ApiAttachment::validateUrl($data['public_url']);
        if ($urlError) {
            return response()->json(['error' => $urlError, 'field' => 'public_url'], 422);
        }

        $user     = $this->apiUser($request);
        $client   = $this->apiClient($request);
        $category = $data['category'] ?? 'other';

        // Detect sensitive document warnings
        $warnings = ApiAttachment::detectSensitiveWarnings($data['filename'], $category);
        $status   = count($warnings) > 0 ? 'warning' : 'valid';

        $attachment = ApiAttachment::create([
            'tenant_id'             => $user->tenant_id,
            'user_id'               => $user->id,
            'added_by_api_client_id'=> $client->id,
            'filename'              => $data['filename'],
            'public_url'            => $data['public_url'],
            'mime_type'             => $data['mime_type'],
            'size_bytes'            => $data['size_bytes'],
            'category'              => $category,
            'notes'                 => $data['notes'] ?? null,
            'validation_status'     => $status,
            'validation_warnings'   => count($warnings) > 0 ? $warnings : null,
        ]);

        $this->audit($request, 'create_attachment', 'api_attachment', $attachment->id, 'low',
            "filename={$attachment->filename}, size={$attachment->size_bytes}",
            "id={$attachment->id}, status={$status}");

        return response()->json([
            'data'    => $this->format($attachment),
            'message' => count($warnings) > 0
                ? 'Attachment registered with warnings. Review before attaching to cold outreach.'
                : 'Attachment registered.',
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user       = $this->apiUser($request);
        $attachment = ApiAttachment::where('user_id', $user->id)->findOrFail($id);

        return response()->json(['data' => $this->format($attachment)]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user       = $this->apiUser($request);
        $attachment = ApiAttachment::where('user_id', $user->id)->findOrFail($id);

        $this->audit($request, 'delete_attachment', 'api_attachment', $attachment->id, 'medium',
            "id={$attachment->id}", 'deleted');

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }

    public function format(ApiAttachment $a): array
    {
        return [
            'id'                   => $a->id,
            'filename'             => $a->filename,
            'public_url'           => $a->public_url,
            'mime_type'            => $a->mime_type,
            'size_bytes'           => $a->size_bytes,
            'category'             => $a->category,
            'notes'                => $a->notes,
            'validation_status'    => $a->validation_status,
            'validation_warnings'  => $a->validation_warnings ?? [],
            'created_at'           => $a->created_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Stub controller for multi-step AI confirmation flows.
 * Stores a pending action in cache keyed by a short UUID.
 * The user approves/rejects from the CRM UI, not via this API.
 */
class ConfirmationController extends GptController
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action'      => 'required|string|max:100',
            'description' => 'required|string|max:1000',
            'payload'     => 'nullable|array',
        ]);

        $id = Str::uuid()->toString();

        Cache::put("gpt_confirmation:{$id}", [
            'id'          => $id,
            'user_id'     => $this->apiUser($request)->id,
            'client_id'   => $this->apiClient($request)->id,
            'action'      => $data['action'],
            'description' => $data['description'],
            'payload'     => $data['payload'] ?? [],
            'status'      => 'pending',
            'created_at'  => now()->toISOString(),
        ], now()->addHours(24));

        $this->audit($request, 'create_confirmation', null, null, 'medium', $data['action']);

        return response()->json([
            'id'          => $id,
            'status'      => 'pending',
            'message'     => 'Confirmation request created. The user must approve this action in the CRM.',
            'review_url'  => url("/settings/integrations/confirmations/{$id}"),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $confirmation = Cache::get("gpt_confirmation:{$id}");

        if (! $confirmation) {
            return response()->json(['error' => 'Confirmation not found or expired.'], 404);
        }

        if ((int) $confirmation['user_id'] !== $this->apiUser($request)->id) {
            return response()->json(['error' => 'Not found.'], 404);
        }

        return response()->json(['data' => $confirmation]);
    }
}

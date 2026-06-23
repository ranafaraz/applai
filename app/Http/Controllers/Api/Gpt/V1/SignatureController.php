<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\EmailSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignatureController extends GptController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $signatures = EmailSignature::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->listResponse($signatures->map(fn ($s) => $this->format($s))->values(), $signatures->count());
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user      = $this->apiUser($request);
        $signature = EmailSignature::where('user_id', $user->id)->findOrFail($id);

        return response()->json(['data' => $this->format($signature)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'body'       => 'required|string|max:50000',
            'is_default' => 'nullable|boolean',
        ]);

        $user = $this->apiUser($request);

        if (! empty($data['is_default'])) {
            EmailSignature::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $signature = EmailSignature::create([
            'user_id'    => $user->id,
            'tenant_id'  => $user->tenant_id,
            'name'       => $data['name'],
            'body'       => $data['body'],
            'is_default' => $data['is_default'] ?? false,
        ]);

        $this->audit($request, 'create_signature', 'email_signature', $signature->id, 'low',
            "name={$signature->name}", "id={$signature->id}");

        return response()->json(['data' => $this->format($signature)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user      = $this->apiUser($request);
        $signature = EmailSignature::where('user_id', $user->id)->findOrFail($id);

        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'body'       => 'sometimes|string|max:50000',
            'is_default' => 'sometimes|boolean',
        ]);

        if (! empty($data['is_default'])) {
            EmailSignature::where('user_id', $user->id)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $signature->update($data);

        $this->audit($request, 'update_signature', 'email_signature', $signature->id, 'low',
            implode(',', array_keys($data)), "id={$signature->id}");

        return response()->json(['data' => $this->format($signature->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user      = $this->apiUser($request);
        $signature = EmailSignature::where('user_id', $user->id)->findOrFail($id);

        $this->audit($request, 'delete_signature', 'email_signature', $signature->id, 'medium',
            "id={$signature->id}", 'deleted');

        $signature->delete();

        return response()->json(['message' => 'Signature deleted.']);
    }

    private function format(EmailSignature $s): array
    {
        return [
            'id'         => $s->id,
            'name'       => $s->name,
            'body'       => $s->body,
            'is_default' => $s->is_default,
            'rendered'   => $s->renderHtml(),
            'created_at' => $s->created_at?->toISOString(),
            'updated_at' => $s->updated_at?->toISOString(),
        ];
    }
}

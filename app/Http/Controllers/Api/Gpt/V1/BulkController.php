<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Batch update/delete across the core CRM entities so an agent can act on many
 * rows in one call instead of N round-trips. Every row is independently scoped
 * to the calling user, processed individually, and reported per-id — a bad or
 * non-owned id fails that single row, never the whole batch.
 */
class BulkController extends GptController
{
    /** entity key => [model class, audit entity type]. */
    private const ENTITIES = [
        'opportunities' => [Opportunity::class, 'opportunity'],
        'contacts'      => [Contact::class, 'contact'],
        'follow_ups'    => [FollowUp::class, 'follow_up'],
    ];

    public function handle(Request $request): JsonResponse
    {
        $base = $request->validate([
            'entity'    => ['required', Rule::in(array_keys(self::ENTITIES))],
            'operation' => ['required', Rule::in(['update', 'delete'])],
            'ids'       => 'required|array|min:1|max:100',
            'ids.*'     => 'integer',
            'data'      => 'required_if:operation,update|array',
        ]);

        $user                 = $this->apiUser($request);
        $entity               = $base['entity'];
        $operation            = $base['operation'];
        [$modelClass, $audit] = self::ENTITIES[$entity];

        $updateData = [];
        if ($operation === 'update') {
            $updateData = $this->validateUpdateData($request, $entity);
            if (empty($updateData)) {
                return response()->json(['error' => 'No updatable fields provided in data.'], 422);
            }
        }

        $ids       = array_values(array_unique(array_map('intval', $base['ids'])));
        $results   = [];
        $succeeded = 0;

        foreach ($ids as $id) {
            /** @var \Illuminate\Database\Eloquent\Model|null $model */
            $model = $modelClass::where('user_id', $user->id)->find($id);

            if (! $model) {
                $results[] = ['id' => $id, 'status' => 'error', 'error' => 'not_found'];
                continue;
            }

            try {
                if ($operation === 'delete') {
                    $model->delete();
                } else {
                    $this->applyUpdate($entity, $model, $updateData);
                    $model->save();
                }
                $results[] = ['id' => $id, 'status' => 'ok'];
                $succeeded++;
            } catch (\Throwable $e) {
                $results[] = ['id' => $id, 'status' => 'error', 'error' => 'failed'];
            }
        }

        $failed = count($ids) - $succeeded;

        $this->audit($request, "bulk_{$operation}", $audit, null, 'medium',
            'ids=' . implode(',', $ids) . ($operation === 'update' ? '; fields=' . implode(',', array_keys($updateData)) : ''),
            "succeeded={$succeeded}, failed={$failed}");

        return response()->json([
            'entity'    => $entity,
            'operation' => $operation,
            'requested' => count($ids),
            'succeeded' => $succeeded,
            'failed'    => $failed,
            'results'   => $results,
        ]);
    }

    /** Validate the `data` payload against the target entity's allowed fields. */
    private function validateUpdateData(Request $request, string $entity): array
    {
        $rules = match ($entity) {
            'opportunities' => [
                'title'        => 'sometimes|string|max:255',
                'organization' => 'sometimes|string|max:255',
                'description'  => 'sometimes|nullable|string|max:5000',
                'url'          => 'sometimes|nullable|url|max:2048',
                'status'       => ['sometimes', Rule::in(['draft', 'active', 'waiting_reply', 'replied', 'interview', 'offer', 'rejected', 'withdrawn', 'closed'])],
                'priority'     => ['sometimes', Rule::in(['low', 'medium', 'high', 'urgent'])],
                'deadline'     => 'sometimes|nullable|date',
                'notes'        => 'sometimes|nullable|string|max:5000',
            ],
            'contacts' => [
                'status'    => ['sometimes', Rule::in(['active', 'suppressed', 'bounced'])],
                'company'   => 'sometimes|nullable|string|max:255',
                'job_title' => 'sometimes|nullable|string|max:255',
                'phone'     => 'sometimes|nullable|string|max:50',
                'notes'     => 'sometimes|nullable|string|max:5000',
            ],
            'follow_ups' => [
                'status'            => 'sometimes|in:pending,sent,cancelled,completed',
                'due_at'            => 'sometimes|date',
                'suggested_subject' => 'sometimes|nullable|string|max:500',
                'suggested_body'    => 'sometimes|nullable|string|max:20000',
            ],
        };

        return Validator::make($request->input('data', []), $rules)->validate();
    }

    /** Apply validated update fields to a single row, handling per-entity mapping. */
    private function applyUpdate(string $entity, $model, array $data): void
    {
        if ($entity === 'follow_ups') {
            // FollowUp stores suggested_subject/body under subject/body columns.
            if (array_key_exists('suggested_subject', $data)) {
                $model->subject = $data['suggested_subject'];
                unset($data['suggested_subject']);
            }
            if (array_key_exists('suggested_body', $data)) {
                $model->body = $data['suggested_body'];
                unset($data['suggested_body']);
            }
        }

        $model->fill($data);

        if ($entity === 'opportunities') {
            $model->last_activity_at = now();
        }
    }
}

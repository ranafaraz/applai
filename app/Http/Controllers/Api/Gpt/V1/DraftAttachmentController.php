<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\ApiAttachment;
use App\Models\EmailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftAttachmentController extends GptController
{
    public function index(Request $request, int $draftId): JsonResponse
    {
        $user  = $this->apiUser($request);
        $draft = EmailMessage::where('user_id', $user->id)->where('status', 'draft')->findOrFail($draftId);

        $attachments = $draft->apiAttachments()->get();

        return $this->listResponse(
            $attachments->map(fn ($a) => $this->formatAttachment($a))->values(),
            $attachments->count(),
            null,
            ['draft_id' => $draft->id],
        );
    }

    public function store(Request $request, int $draftId): JsonResponse
    {
        $data = $request->validate([
            'attachment_ids'   => 'required|array|min:1|max:10',
            'attachment_ids.*' => 'integer',
        ]);

        $user  = $this->apiUser($request);
        $draft = EmailMessage::where('user_id', $user->id)->where('status', 'draft')->findOrFail($draftId);

        // Verify all attachments belong to this user
        $attachments = ApiAttachment::where('user_id', $user->id)
            ->whereIn('id', $data['attachment_ids'])
            ->get();

        $found    = $attachments->pluck('id')->toArray();
        $notFound = array_diff($data['attachment_ids'], $found);
        if (! empty($notFound)) {
            return response()->json([
                'error'        => 'One or more attachment IDs not found.',
                'missing_ids'  => array_values($notFound),
            ], 422);
        }

        // Warn if any attachment has sensitive warnings
        $allWarnings = $attachments
            ->where('validation_status', 'warning')
            ->flatMap(fn ($a) => $a->validation_warnings ?? [])
            ->values()
            ->toArray();

        // Attach (skip duplicates via sync without detach)
        $syncData = [];
        foreach ($attachments as $att) {
            $syncData[$att->id] = ['added_by_user_id' => $user->id];
        }
        $draft->apiAttachments()->syncWithoutDetaching($syncData);

        $this->audit($request, 'attach_attachments_to_draft', 'email_message', $draft->id, 'medium',
            'attachment_ids=' . implode(',', $found),
            "draft_id={$draft->id}");

        $response = [
            'draft_id'       => $draft->id,
            'attachment_ids' => $found,
            'message'        => count($found) . ' attachment(s) linked to draft.',
        ];

        if (! empty($allWarnings)) {
            $response['attachment_validation_warnings'] = $allWarnings;
            $response['warning'] = 'Some attachments contain sensitive documents. Confirm the recipient has requested these before sending.';
            $response['confirmation_required'] = true;
        }

        return response()->json($response, 201);
    }

    public function destroy(Request $request, int $draftId, int $attachmentId): JsonResponse
    {
        $user       = $this->apiUser($request);
        $draft      = EmailMessage::where('user_id', $user->id)->where('status', 'draft')->findOrFail($draftId);
        $attachment = ApiAttachment::where('user_id', $user->id)->findOrFail($attachmentId);

        $draft->apiAttachments()->detach($attachmentId);

        $this->audit($request, 'detach_attachment_from_draft', 'email_message', $draft->id, 'low',
            "attachment_id={$attachmentId}", "draft_id={$draft->id}");

        return response()->json(['message' => 'Attachment removed from draft.']);
    }

    private function formatAttachment(ApiAttachment $a): array
    {
        return [
            'id'                  => $a->id,
            'filename'            => $a->filename,
            'public_url'          => $a->public_url,
            'mime_type'           => $a->mime_type,
            'size_bytes'          => $a->size_bytes,
            'category'            => $a->category,
            'validation_status'   => $a->validation_status,
            'validation_warnings' => $a->validation_warnings ?? [],
            'added_by_user_id'    => $a->pivot?->added_by_user_id,
        ];
    }
}

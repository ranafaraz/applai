<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\ApiAttachment;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailSignature;
use App\Models\Opportunity;
use App\Models\SuppressionList;
use App\Models\TimelineEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailDraftController extends GptController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $drafts = EmailMessage::where('user_id', $user->id)
            ->where('status', 'draft')
            ->where('direction', 'outbound')
            ->with(['contact', 'opportunity', 'emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data'  => $drafts->map(fn ($d) => $this->format($d)),
            'count' => $drafts->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_id'      => 'required|integer',
            'opportunity_id'  => 'nullable|integer',
            'subject'         => 'required|string|max:500',
            'body'            => 'required|string|max:50000',
            'draft_type'      => ['nullable', Rule::in(['initial_outreach', 'follow_up', 'thank_you', 'general'])],
            'tone'            => ['nullable', Rule::in(['professional', 'casual', 'formal'])],
            'requires_review' => 'nullable|boolean',
            'signature_id'    => 'nullable|integer',
            'attachment_ids'  => 'nullable|array|max:10',
            'attachment_ids.*'=> 'integer',
        ]);

        $user = $this->apiUser($request);

        // Verify contact ownership
        $contact = Contact::where('user_id', $user->id)->findOrFail($data['contact_id']);

        // Block suppressed contacts
        $suppressed = in_array($contact->status, ['suppressed', 'bounced'], true);
        if (! $suppressed && $contact->email) {
            $suppressed = SuppressionList::isSuppressed($user->id, $contact->email);
        }

        if ($suppressed) {
            $this->audit($request, 'create_draft_blocked', 'contact', $contact->id, 'medium',
                "contact_id={$contact->id}", 'blocked: suppressed contact', 'blocked');
            return response()->json([
                'error'   => 'Cannot create draft for a suppressed, bounced, or unsubscribed contact.',
                'contact' => ['id' => $contact->id, 'status' => $contact->status],
            ], 422);
        }

        // Verify opportunity ownership if provided
        $opportunity = null;
        if (! empty($data['opportunity_id'])) {
            $opportunity = Opportunity::where('user_id', $user->id)->findOrFail($data['opportunity_id']);
        }

        // Resolve signature and snapshot its rendered HTML
        $signature         = null;
        $renderedSignature = null;
        if (! empty($data['signature_id'])) {
            $signature         = EmailSignature::where('user_id', $user->id)->findOrFail($data['signature_id']);
            $renderedSignature = $signature->renderHtml();
        }

        // Resolve sender account
        $emailAccount = EmailAccount::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        $draft = EmailMessage::create([
            'user_id'            => $user->id,
            'tenant_id'          => $user->tenant_id,
            'email_account_id'   => $emailAccount?->id,
            'contact_id'         => $contact->id,
            'opportunity_id'     => $opportunity?->id,
            'email_signature_id' => $signature?->id,
            'rendered_signature' => $renderedSignature,
            'subject'            => $data['subject'],
            'body'               => $data['body'],
            'to_email'           => $contact->email,
            'to_name'            => $contact->full_name,
            'status'             => 'draft',
            'direction'          => 'outbound',
            'is_follow_up'       => ($data['draft_type'] ?? '') === 'follow_up',
        ]);

        // Attach any provided attachments
        $attachmentWarnings = [];
        if (! empty($data['attachment_ids'])) {
            $attachments = ApiAttachment::where('user_id', $user->id)
                ->whereIn('id', $data['attachment_ids'])
                ->get();

            $missingIds = array_values(array_diff($data['attachment_ids'], $attachments->pluck('id')->toArray()));
            if (! empty($missingIds)) {
                $draft->forceDelete();
                return response()->json([
                    'error'       => 'One or more attachment_ids were not found. Use POST /attachments to register files and get valid IDs. Document IDs from POST /documents are not interchangeable with attachment IDs.',
                    'missing_ids' => $missingIds,
                ], 422);
            }

            $syncData = [];
            foreach ($attachments as $att) {
                $syncData[$att->id] = ['added_by_user_id' => $user->id];
                if ($att->validation_status === 'warning') {
                    $attachmentWarnings = array_merge($attachmentWarnings, $att->validation_warnings ?? []);
                }
            }
            $draft->apiAttachments()->sync($syncData);
        }

        // Keep opportunity–contact pivot in sync automatically.
        if ($opportunity) {
            $opportunity->contacts()->syncWithoutDetaching([$contact->id]);
        }

        TimelineEvent::create([
            'user_id'           => $user->id,
            'tenant_id'         => $user->tenant_id,
            'timelineable_type' => EmailMessage::class,
            'timelineable_id'   => $draft->id,
            'event_type'        => 'draft_created',
            'description'       => "Email draft created via AI integration ({$this->apiClient($request)->source_type}). Subject: {$draft->subject}",
            'happened_at'       => now(),
        ]);

        $this->audit($request, 'create_draft', 'email_message', $draft->id, 'medium',
            "contact_id={$contact->id}, opportunity_id=" . ($opportunity?->id ?? 'null') .
            ", signature_id=" . ($signature?->id ?? 'null') .
            ", attachment_count=" . count($data['attachment_ids'] ?? []),
            "draft_id={$draft->id}");

        $draft->load(['emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

        $response = [
            'data'                 => $this->format($draft),
            'confirmation_required'=> true,
            'send_status'          => 'draft',
            'message'              => 'Draft saved. Review it in the CRM before sending.',
        ];

        if (! empty($attachmentWarnings)) {
            $response['attachment_validation_warnings'] = array_values(array_unique($attachmentWarnings));
            $response['warning'] = 'Some attachments contain sensitive documents. Confirm the recipient has requested these before sending.';
        }

        return response()->json($response, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'subject'         => 'sometimes|string|max:500',
            'body'            => 'sometimes|string|max:50000',
            'signature_id'    => 'sometimes|nullable|integer',
            'attachment_ids'  => 'sometimes|array|max:10',
            'attachment_ids.*'=> 'integer',
        ]);

        $user  = $this->apiUser($request);
        $draft = EmailMessage::where('user_id', $user->id)
            ->where('status', 'draft')
            ->where('direction', 'outbound')
            ->findOrFail($id);

        if (array_key_exists('subject', $data)) {
            $draft->subject = $data['subject'];
        }
        if (array_key_exists('body', $data)) {
            $draft->body = $data['body'];
        }

        // Re-resolve signature and re-snapshot its rendered HTML (null clears it).
        if (array_key_exists('signature_id', $data)) {
            if (empty($data['signature_id'])) {
                $draft->email_signature_id = null;
                $draft->rendered_signature = null;
            } else {
                $signature = EmailSignature::where('user_id', $user->id)->findOrFail($data['signature_id']);
                $draft->email_signature_id = $signature->id;
                $draft->rendered_signature = $signature->renderHtml();
            }
        }

        $draft->save();

        // Replace the attachment set when provided (verifying ownership).
        if (array_key_exists('attachment_ids', $data)) {
            $attachments = ApiAttachment::where('user_id', $user->id)
                ->whereIn('id', $data['attachment_ids'])
                ->get();

            $missingIds = array_values(array_diff($data['attachment_ids'], $attachments->pluck('id')->toArray()));
            if (! empty($missingIds)) {
                return response()->json([
                    'error'       => 'One or more attachment_ids were not found. Register files via POST /attachments first.',
                    'missing_ids' => $missingIds,
                ], 422);
            }

            $syncData = [];
            foreach ($attachments as $att) {
                $syncData[$att->id] = ['added_by_user_id' => $user->id];
            }
            $draft->apiAttachments()->sync($syncData);
        }

        $this->audit($request, 'update_draft', 'email_message', $draft->id, 'low',
            'fields=' . implode(',', array_keys($data)), "draft_id={$draft->id}");

        $draft->load(['emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion']);

        return response()->json([
            'data'                  => $this->format($draft),
            'confirmation_required' => true,
            'send_status'           => 'draft',
            'message'               => 'Draft updated. Review it in the CRM before sending.',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user  = $this->apiUser($request);
        $draft = EmailMessage::where('user_id', $user->id)
            ->where('status', 'draft')
            ->where('direction', 'outbound')
            ->findOrFail($id);

        $draft->delete();

        $this->audit($request, 'delete_draft', 'email_message', $id, 'low', "draft_id={$id}");

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Human-triggered send. Hands the draft to the existing scheduled-send
     * pipeline (crm:send-scheduled → SendEmailJob) by marking it scheduled for
     * now. This endpoint never dispatches mail directly. Scope: email:send.
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $user  = $this->apiUser($request);
        $draft = EmailMessage::where('user_id', $user->id)
            ->where('direction', 'outbound')
            ->findOrFail($id);

        if ($draft->status !== 'draft') {
            return response()->json([
                'error'        => 'Only a pending draft can be queued for send.',
                'current_status' => $draft->status,
            ], 422);
        }

        if (empty($draft->to_email)) {
            return response()->json(['error' => 'Draft has no recipient email address.'], 422);
        }

        // Block suppressed recipients at send time as a final guard.
        if (SuppressionList::isSuppressed($user->id, $draft->to_email)) {
            $this->audit($request, 'send_draft_blocked', 'email_message', $draft->id, 'high',
                "to={$draft->to_email}", 'blocked: suppressed', 'blocked');
            return response()->json(['error' => 'Recipient is on the suppression list.'], 422);
        }

        $draft->status       = 'scheduled';
        $draft->scheduled_at = now();
        $draft->save();

        TimelineEvent::create([
            'user_id'           => $user->id,
            'tenant_id'         => $user->tenant_id,
            'timelineable_type' => EmailMessage::class,
            'timelineable_id'   => $draft->id,
            'event_type'        => 'send_requested',
            'description'       => "Send requested via AI integration ({$this->apiClient($request)->source_type}). Subject: {$draft->subject}",
            'happened_at'       => now(),
        ]);

        $this->audit($request, 'send_draft', 'email_message', $draft->id, 'high',
            "to={$draft->to_email}", "draft_id={$draft->id} queued");

        return response()->json([
            'queued'    => true,
            'draft_id'  => $draft->id,
            'notice'    => 'Email queued for send. You will be notified on delivery.',
        ]);
    }

    public function format(EmailMessage $d): array
    {
        $attachments    = $d->relationLoaded('apiAttachments') ? $d->apiAttachments : collect();
        $attachmentIds  = $attachments->pluck('id')->toArray();
        $hasWarnings    = $attachments->where('validation_status', 'warning')->isNotEmpty();
        $linkedDocuments = $d->relationLoaded('apiDocumentLinks')
            ? $this->formatLinkedDocuments($d->apiDocumentLinks)
            : [];

        return [
            'id'                           => $d->id,
            'subject'                      => $d->subject,
            'to_email'                     => $d->to_email,
            'to_name'                      => $d->to_name,
            'status'                       => $d->status,
            'send_status'                  => $d->status,
            'contact_id'                   => $d->contact_id,
            'opportunity_id'               => $d->opportunity_id,
            'signature_id'                 => $d->email_signature_id,
            'signature_name'               => $d->emailSignature?->name,
            'rendered_signature'           => $d->rendered_signature,
            'attachment_ids'               => $attachmentIds,
            'attachment_count'             => count($attachmentIds),
            'attachment_validation_status' => $hasWarnings ? 'warning' : 'valid',
            'linked_documents'             => $linkedDocuments,
            'linked_document_count'        => count($linkedDocuments),
            'linked_documents_notice'      => 'linked_documents are reference files attached via uploadDocument — they are NOT sent with this email. Only items in attachment_ids (added via uploadAttachment + attachment_ids) are sent.',
            'confirmation_required'        => true,
            'is_follow_up'                 => $d->is_follow_up,
            'created_at'                   => $d->created_at?->toISOString(),
            'preview'                      => substr(strip_tags($d->body ?? ''), 0, 200),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\AiActionAuditLog;
use App\Models\EmailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DraftPreviewController extends GptController
{
    public function show(Request $request, int $id): JsonResponse
    {
        $user  = $this->apiUser($request);
        $draft = EmailMessage::where('user_id', $user->id)
            ->where('status', 'draft')
            ->with(['contact', 'opportunity', 'emailSignature', 'apiAttachments', 'apiDocumentLinks.document.currentVersion'])
            ->findOrFail($id);

        // Rendered body = raw body + snapshot signature (if captured) or live render
        $renderedSignature = $draft->rendered_signature
            ?? $draft->emailSignature?->renderHtml()
            ?? null;

        $renderedBody = $draft->body . ($renderedSignature ?? '');

        // Collect attachment validation issues
        $attachmentWarnings = $draft->apiAttachments
            ->where('validation_status', 'warning')
            ->flatMap(fn ($a) => $a->validation_warnings ?? [])
            ->values()
            ->toArray();

        // Retrieve the most recent audit log entry for this draft
        $auditRef = AiActionAuditLog::where('entity_type', 'email_message')
            ->where('entity_id', $draft->id)
            ->orderByDesc('created_at')
            ->value('id');

        $attachments = $draft->apiAttachments->map(fn ($a) => [
            'id'                  => $a->id,
            'filename'            => $a->filename,
            'public_url'          => $a->public_url,
            'mime_type'           => $a->mime_type,
            'size_bytes'          => $a->size_bytes,
            'category'            => $a->category,
            'validation_status'   => $a->validation_status,
            'validation_warnings' => $a->validation_warnings ?? [],
        ])->values()->toArray();

        $linkedDocuments = $this->formatLinkedDocuments($draft->apiDocumentLinks);

        return response()->json([
            'draft_id'                     => $draft->id,
            'send_status'                  => $draft->status,
            'confirmation_required'        => true,
            'to_email'                     => $draft->to_email,
            'to_name'                      => $draft->to_name,
            'subject'                      => $draft->subject,
            'body_preview'                 => $draft->body,
            'rendered_signature'           => $renderedSignature,
            'rendered_body'                => $renderedBody,
            'signature_id'                 => $draft->email_signature_id,
            'signature_name'               => $draft->emailSignature?->name,
            'attachment_ids'               => $draft->apiAttachments->pluck('id')->toArray(),
            'attachments'                  => $attachments,
            'attachment_count'             => count($attachments),
            'attachment_validation_status' => count($attachmentWarnings) > 0 ? 'warning' : 'valid',
            'attachment_validation_warnings' => $attachmentWarnings,
            'linked_documents'             => $linkedDocuments,
            'linked_document_count'        => count($linkedDocuments),
            'contact_id'                   => $draft->contact_id,
            'opportunity_id'               => $draft->opportunity_id,
            'created_at'                   => $draft->created_at?->toISOString(),
            'audit_log_reference'          => $auditRef,
            'notice'                       => 'This draft requires explicit user review and confirmation before sending. Auto-sending is disabled.',
            'linked_documents_notice'      => 'linked_documents are reference files attached via uploadDocument — they are NOT sent with this email. Only items in attachments/attachment_ids (added via uploadAttachment + attachment_ids) are sent.',
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\EmailMessage;
use App\Models\FollowUp;
use App\Models\InboxMessage;
use App\Models\Opportunity;
use App\Models\SuppressionList;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class FollowUpService
{
    public function __construct(
        private readonly EmailSendingService $emailSendingService,
    ) {}

    /**
     * Schedule a follow-up for an outbound email.
     *
     * @param  EmailMessage $originalEmail  The outbound email that was just sent.
     * @param  int          $followUpNumber Which follow-up in the sequence (1, 2, 3 …).
     * @param  int|null     $daysDelay      Days until due; falls back to the user's default setting.
     */
    public function scheduleFollowUp(
        EmailMessage $originalEmail,
        int $followUpNumber = 1,
        ?int $daysDelay = null,
    ): FollowUp {
        $originalEmail->load('emailAccount', 'contact', 'opportunity');

        // Resolve delay: explicit arg → user setting → hard default (3 days)
        if ($daysDelay === null) {
            $setting   = $originalEmail->user?->setting;
            $daysDelay = $setting?->default_follow_up_days ?? 3;
        }

        $dueAt = Carbon::now()->addDays($daysDelay);

        return FollowUp::create([
            'tenant_id'         => $originalEmail->tenant_id,
            'user_id'          => $originalEmail->user_id,
            'opportunity_id'   => $originalEmail->opportunity_id,
            'contact_id'       => $originalEmail->contact_id,
            'email_account_id' => $originalEmail->email_account_id,
            'email_message_id' => $originalEmail->id,
            'follow_up_number' => $followUpNumber,
            'due_at'           => $dueAt,
            'status'           => 'pending',
            'subject'          => $this->buildFollowUpSubject($originalEmail, $followUpNumber),
            'body'             => $this->buildFollowUpBody($originalEmail, $followUpNumber),
        ]);
    }

    /**
     * Process all pending follow-ups that are due now (or in the past).
     * Intended to be called by the ProcessFollowUpsJob on a schedule.
     */
    public function processDueFollowUps(): void
    {
        $maxFollowUps = 3;

        $dueFollowUps = FollowUp::query()
            ->with(['opportunity', 'contact', 'emailAccount', 'emailMessage'])
            ->where('status', 'pending')
            ->where('due_at', '<=', now())
            ->get();

        foreach ($dueFollowUps as $followUp) {
            try {
                $this->processSingleFollowUp($followUp, $maxFollowUps);
            } catch (Throwable $e) {
                Log::error('FollowUpService: failed to process follow-up', [
                    'follow_up_id' => $followUp->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Cancel all pending follow-ups for a given Opportunity.
     */
    public function cancelForOpportunity(Opportunity $opportunity): void
    {
        FollowUp::query()
            ->where('opportunity_id', $opportunity->id)
            ->where('status', 'pending')
            ->update([
                'status'        => 'cancelled',
                'cancel_reason' => 'opportunity_closed',
            ]);
    }

    /**
     * Cancel all pending follow-ups for a given Contact.
     */
    public function cancelForContact(int $contactId): void
    {
        FollowUp::query()
            ->where('contact_id', $contactId)
            ->where('status', 'pending')
            ->update([
                'status'        => 'cancelled',
                'cancel_reason' => 'contact_cancelled',
            ]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function processSingleFollowUp(FollowUp $followUp, int $maxFollowUps): void
    {
        // a. Skip if contact is suppressed
        if ($followUp->contact_id) {
            $contact = $followUp->contact;
            if ($contact && SuppressionList::isSuppressed($followUp->user_id, $contact->email)) {
                $followUp->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => 'contact_suppressed',
                ]);
                return;
            }
        }

        // b. Skip if opportunity is closed/rejected/withdrawn
        if ($followUp->opportunity_id) {
            $opportunity = $followUp->opportunity;
            if ($opportunity && in_array($opportunity->status, ['closed', 'rejected', 'withdrawn'], true)) {
                $followUp->update([
                    'status'        => 'cancelled',
                    'cancel_reason' => 'opportunity_closed',
                ]);
                return;
            }
        }

        // c. Skip if a reply was already received
        if ($this->replyAlreadyReceived($followUp)) {
            $followUp->update([
                'status'        => 'cancelled',
                'cancel_reason' => 'reply_received',
            ]);
            return;
        }

        // d. Build an EmailMessage for the follow-up and send it
        $emailMessage = $this->buildFollowUpEmailMessage($followUp);

        $sent = $this->emailSendingService->sendEmail($emailMessage);

        if ($sent) {
            $followUp->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

            // e. Schedule next follow-up if under the limit
            if ($followUp->follow_up_number < $maxFollowUps) {
                $this->scheduleFollowUp(
                    $emailMessage,
                    $followUp->follow_up_number + 1,
                );
            }
        } else {
            $followUp->update(['status' => 'failed']);
        }
    }

    /**
     * Check whether a reply has already been received for the opportunity+contact pair.
     */
    private function replyAlreadyReceived(FollowUp $followUp): bool
    {
        $query = InboxMessage::query()
            ->whereNotNull('matched_outbound_id');

        if ($followUp->opportunity_id) {
            $query->where('matched_opportunity_id', $followUp->opportunity_id);
        }

        if ($followUp->contact_id) {
            $query->where('matched_contact_id', $followUp->contact_id);
        }

        return $query->exists();
    }

    /**
     * Create a child EmailMessage record representing this follow-up send attempt.
     */
    private function buildFollowUpEmailMessage(FollowUp $followUp): EmailMessage
    {
        $contact = $followUp->contact;

        /** @var EmailMessage $emailMessage */
        $emailMessage = EmailMessage::create([
            'tenant_id'         => $followUp->tenant_id,
            'user_id'          => $followUp->user_id,
            'email_account_id' => $followUp->email_account_id,
            'contact_id'       => $followUp->contact_id,
            'opportunity_id'   => $followUp->opportunity_id,
            'email_signature_id' => $followUp->email_signature_id,
            'rendered_signature' => $followUp->rendered_signature,
            'subject'          => $followUp->subject,
            'body'             => $followUp->body,
            'to_email'         => $contact?->email ?? '',
            'to_name'          => $contact?->full_name ?? '',
            'status'           => 'scheduled',
            'direction'        => 'outbound',
            'is_follow_up'     => true,
            'follow_up_number' => $followUp->follow_up_number,
            'parent_message_id'=> $followUp->email_message_id,
        ]);

        $syncData = $followUp->apiAttachments()
            ->pluck('api_attachments.id')
            ->mapWithKeys(fn (int $id) => [$id => ['added_by_user_id' => $followUp->user_id]])
            ->all();

        if (! empty($syncData)) {
            $emailMessage->apiAttachments()->sync($syncData);
        }

        return $emailMessage;
    }

    private function buildFollowUpSubject(EmailMessage $original, int $followUpNumber): string
    {
        $prefix = $followUpNumber === 1 ? 'Re: ' : '';
        return $prefix . $original->subject;
    }

    private function buildFollowUpBody(EmailMessage $original, int $followUpNumber): string
    {
        $intro = match ($followUpNumber) {
            1 => "I wanted to follow up on my previous email.",
            2 => "I'm reaching out one more time regarding my earlier message.",
            default => "This is a final follow-up regarding my earlier outreach.",
        };

        return "<p>{$intro}</p><p>Please let me know if you have any questions or if there's a better time to connect.</p>";
    }
}

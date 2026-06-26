<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Services\EmailSendingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum SMTP retry attempts. Permanent failures (suppression, limits)
     * are caught inside EmailSendingService and never reach this retry counter.
     */
    public int $tries = 2;

    /** Seconds between retries (attempt 2 after 60 s, attempt 3 after 120 s). */
    public int $backoff = 60;

    public function __construct(
        public readonly EmailMessage $emailMessage,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailSendingService $emailSendingService): void
    {
        Log::info('SendEmailJob: attempting to send email', [
            'email_message_id' => $this->emailMessage->id,
            'to_email'         => $this->emailMessage->to_email,
            'attempt'          => $this->attempts(),
        ]);

        $emailSendingService->sendEmail($this->emailMessage);
    }

    /**
     * Called after all retry attempts are exhausted.
     * EmailSendingService already marked the email 'failed' on the last attempt;
     * this just ensures the failure_reason reflects the final SMTP error and logs it.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('SendEmailJob: permanently failed after all retries', [
            'email_message_id' => $this->emailMessage->id,
            'attempts'         => $this->tries,
            'error'            => $exception?->getMessage(),
        ]);

        $this->emailMessage->refresh();

        $finalReason = $exception?->getMessage()
            ?? 'Failed after ' . $this->tries . ' attempts. Check your SMTP credentials.';

        $this->emailMessage->update([
            'status'         => 'failed',
            'failed_at'      => now(),
            'failure_reason' => $finalReason,
        ]);
    }
}

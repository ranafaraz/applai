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
     * Maximum number of attempts before the job is considered permanently failed.
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retrying after a failure (exponential-friendly base).
     */
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
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('SendEmailJob: permanently failed after all retries', [
            'email_message_id' => $this->emailMessage->id,
            'error'            => $exception?->getMessage(),
        ]);

        // Mark as failed if EmailSendingService hasn't already done so
        if ($this->emailMessage->status !== 'failed') {
            $this->emailMessage->update([
                'status'         => 'failed',
                'failed_at'      => now(),
                'failure_reason' => $exception?->getMessage() ?? 'Job failed after maximum retries.',
            ]);
        }
    }
}

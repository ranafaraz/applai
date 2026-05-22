<?php

namespace App\Jobs;

use App\Services\EmailSendingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResetDailyEmailCountersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * No constructor arguments – resets counters for all email accounts globally.
     */
    public function __construct() {}

    /**
     * Execute the job.
     *
     * Delegates to EmailSendingService::resetDailyCounters() which updates
     * emails_sent_today = 0 and last_reset_at = now() for every EmailAccount
     * whose counter was last reset before today.
     */
    public function handle(EmailSendingService $emailSendingService): void
    {
        Log::info('ResetDailyEmailCountersJob: resetting daily send counters');

        try {
            $emailSendingService->resetDailyCounters();
            Log::info('ResetDailyEmailCountersJob: counters reset successfully');
        } catch (Throwable $e) {
            Log::error('ResetDailyEmailCountersJob: failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ResetDailyEmailCountersJob: permanently failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}

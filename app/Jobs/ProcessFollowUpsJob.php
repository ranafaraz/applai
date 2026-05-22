<?php

namespace App\Jobs;

use App\Services\FollowUpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessFollowUpsJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Allow up to 5 minutes to process all due follow-ups.
     */
    public int $timeout = 300;

    public int $tries = 1;

    /**
     * No constructor arguments — this job processes all due follow-ups globally.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(FollowUpService $followUpService): void
    {
        Log::info('ProcessFollowUpsJob: starting to process due follow-ups');

        try {
            $followUpService->processDueFollowUps();
            Log::info('ProcessFollowUpsJob: completed successfully');
        } catch (Throwable $e) {
            Log::error('ProcessFollowUpsJob: failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessFollowUpsJob: permanently failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}

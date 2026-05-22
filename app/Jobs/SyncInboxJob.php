<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Services\ImapSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncInboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow up to 10 minutes for a full IMAP sync.
     */
    public int $timeout = 600;

    /**
     * Only attempt once – if the connection fails the scheduler will retry on next run.
     */
    public int $tries = 1;

    public function __construct(
        public readonly EmailAccount $emailAccount,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ImapSyncService $imapSyncService): void
    {
        Log::info('SyncInboxJob: starting sync', [
            'email_account_id' => $this->emailAccount->id,
            'email'            => $this->emailAccount->email,
        ]);

        $this->emailAccount->update(['sync_status' => 'syncing']);

        try {
            $stats = $imapSyncService->syncAccount($this->emailAccount);

            Log::info('SyncInboxJob: sync completed', array_merge(
                ['email_account_id' => $this->emailAccount->id],
                $stats,
            ));

            $this->emailAccount->update(['sync_status' => 'idle']);

        } catch (Throwable $e) {
            Log::error('SyncInboxJob: sync failed', [
                'email_account_id' => $this->emailAccount->id,
                'error'            => $e->getMessage(),
            ]);

            $this->emailAccount->update(['sync_status' => 'error']);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('SyncInboxJob: permanently failed', [
            'email_account_id' => $this->emailAccount->id,
            'error'            => $exception?->getMessage(),
        ]);

        $this->emailAccount->update(['sync_status' => 'error']);
    }
}

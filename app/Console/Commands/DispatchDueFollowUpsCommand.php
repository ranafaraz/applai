<?php

namespace App\Console\Commands;

use App\Services\FollowUpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchDueFollowUpsCommand extends Command
{
    protected $signature = 'crm:dispatch-due-followups';

    protected $description = 'Dispatch pending auto-send follow-ups that are due now';

    public function handle(FollowUpService $followUpService): int
    {
        try {
            $followUpService->processDueFollowUps();
            $this->info('Due follow-ups dispatched.');
        } catch (Throwable $e) {
            Log::error('crm:dispatch-due-followups failed', ['error' => $e->getMessage()]);
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

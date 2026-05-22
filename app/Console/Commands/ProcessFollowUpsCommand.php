<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Services\FollowUpService;
use Illuminate\Console\Command;
use Throwable;

class ProcessFollowUpsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:process-follow-ups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all pending follow-up emails that are due';

    /**
     * Execute the console command.
     */
    public function handle(FollowUpService $followUpService): int
    {
        // Count how many follow-ups are due before processing so we can report
        $dueCount = FollowUp::where('status', 'pending')
            ->where('due_at', '<=', now())
            ->count();

        if ($dueCount === 0) {
            $this->info('No follow-ups are currently due.');
            return self::SUCCESS;
        }

        $this->info("Processing {$dueCount} due follow-up(s)…");

        try {
            $followUpService->processDueFollowUps();
        } catch (Throwable $e) {
            $this->error("An error occurred while processing follow-ups: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info("Processed {$dueCount} follow-up(s) successfully.");

        return self::SUCCESS;
    }
}

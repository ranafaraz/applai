<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use Illuminate\Console\Command;

class ResetDailyCountersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:reset-daily-counters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset daily email sending counters for all email accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updated = EmailAccount::query()->update([
            'emails_sent_today' => 0,
            'last_reset_at'     => now(),
        ]);

        $this->info("Daily email counters reset for {$updated} account(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\SyncInboxJob;
use App\Models\EmailAccount;
use Illuminate\Console\Command;

class SyncAllInboxesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:sync-inboxes
                            {--account= : The ID of a specific email account to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync IMAP inboxes for all active email accounts or a specific one';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $accountId = $this->option('account');

        $query = EmailAccount::query()->where('is_active', true);

        if ($accountId !== null) {
            $query->where('id', (int) $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('No active email accounts found' . ($accountId ? " with ID {$accountId}" : '') . '.');
            return self::SUCCESS;
        }

        $this->info("Dispatching sync jobs for {$accounts->count()} account(s)…");

        foreach ($accounts as $account) {
            SyncInboxJob::dispatch($account);
            $this->line("  ✔ Queued sync for <comment>{$account->email}</comment> (ID: {$account->id})");
        }

        $this->info('All sync jobs dispatched.');

        return self::SUCCESS;
    }
}

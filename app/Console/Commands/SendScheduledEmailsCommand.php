<?php

namespace App\Console\Commands;

use App\Jobs\SendEmailJob;
use App\Models\EmailMessage;
use Illuminate\Console\Command;

class SendScheduledEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue all scheduled emails that are due to be sent';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $due = EmailMessage::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled emails are currently due.');
            return self::SUCCESS;
        }

        $this->info("Found {$due->count()} scheduled email(s) to dispatch…");

        foreach ($due as $emailMessage) {
            // Mark as queued to prevent duplicate dispatches on the next run
            $emailMessage->update(['status' => 'queued']);

            SendEmailJob::dispatch($emailMessage);

            $this->line("  ✔ Queued email ID {$emailMessage->id} → <comment>{$emailMessage->to_email}</comment>");
        }

        $this->info("Dispatched {$due->count()} email job(s).");

        return self::SUCCESS;
    }
}

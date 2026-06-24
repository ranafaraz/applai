<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UnstickSendingEmailsCommand extends Command
{
    protected $signature = 'crm:unstick-sending-emails
                            {--minutes=10 : Mark emails stuck in "sending" for this many minutes as failed}';

    protected $description = 'Reset emails stuck in "sending" state to "failed" so they can be reviewed/resent';

    public function handle(): int
    {
        $threshold = now()->subMinutes((int) $this->option('minutes'));

        $stuck = EmailMessage::query()
            ->where('status', 'sending')
            ->where('updated_at', '<', $threshold)
            ->get();

        if ($stuck->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($stuck as $email) {
            $email->update([
                'status'         => 'failed',
                'failed_at'      => now(),
                'failure_reason' => 'Send process was interrupted (worker crash or timeout). Please check your email account settings and resend manually.',
            ]);

            Log::warning('UnstickSendingEmailsCommand: reset stuck email to failed', [
                'email_message_id' => $email->id,
                'to_email'         => $email->to_email,
                'stuck_since'      => $email->updated_at->toIso8601String(),
            ]);
        }

        $count = $stuck->count();
        $this->warn("Reset {$count} stuck email(s) from 'sending' → 'failed'.");
        Log::info("UnstickSendingEmailsCommand: reset {$count} stuck email(s) to failed");

        return self::SUCCESS;
    }
}

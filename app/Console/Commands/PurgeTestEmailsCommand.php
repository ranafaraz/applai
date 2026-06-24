<?php

namespace App\Console\Commands;

use App\Models\EmailMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One-shot cleanup of test email records created by automated test scripts
 * or AI agent sessions that used real contacts as test targets.
 *
 * Matches subject prefixes left by known test runs:
 *   [E2E TEST], [E2E], [FORMATTING TEST], [TEST], CRM test —
 *
 * NEVER schedules itself — must be invoked explicitly.
 * Run once on production after deploy: php artisan crm:purge-test-emails
 */
class PurgeTestEmailsCommand extends Command
{
    protected $signature = 'crm:purge-test-emails
                            {--dry-run : List what would be deleted without actually deleting}';

    protected $description = 'Delete email records created by test/dev scripts that used real contacts';

    private const TEST_PREFIXES = [
        '[E2E TEST]',
        '[E2E]',
        '[FORMATTING TEST',
        '[TEST]',
        'CRM test —',
        '[RATE TEST]',
        '[SMOKE TEST]',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $query = EmailMessage::query()->where(function ($q) {
            foreach (self::TEST_PREFIXES as $prefix) {
                $q->orWhere('subject', 'like', $prefix . '%');
            }
        });

        $emails = $query->get(['id', 'subject', 'to_email', 'status', 'created_at']);

        if ($emails->isEmpty()) {
            $this->info('No test email records found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$emails->count()} test email record(s):");
        foreach ($emails as $e) {
            $this->line("  #{$e->id}  [{$e->status}]  {$e->subject}  →  {$e->to_email}  ({$e->created_at})");
        }

        if ($dryRun) {
            $this->warn('Dry-run mode — nothing deleted. Re-run without --dry-run to purge.');
            return self::SUCCESS;
        }

        $ids   = $emails->pluck('id')->all();
        $count = EmailMessage::whereIn('id', $ids)->forceDelete();

        $this->error("Permanently deleted {$count} test email record(s).");
        Log::warning('PurgeTestEmailsCommand: permanently deleted test email records', [
            'count' => $count,
            'ids'   => $ids,
        ]);

        return self::SUCCESS;
    }
}

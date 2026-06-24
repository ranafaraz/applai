<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\InboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safety-net sweep: any opportunity that has a matched inbound reply but still
 * has pending follow-ups gets them cancelled here.  The real-time path is
 * HandleReplyReceived → ImapSyncService::cancelFollowUpsOnReply(); this command
 * catches anything that slipped through (e.g. replies ingested before the listener
 * was deployed, or edge-cases in IMAP matching).
 */
class CancelFollowUpsOnReplyCommand extends Command
{
    protected $signature = 'crm:cancel-followups-on-reply';

    protected $description = 'Cancel pending follow-ups for opportunities that have received a reply';

    public function handle(): int
    {
        $opportunityIds = InboxMessage::query()
            ->whereNotNull('matched_opportunity_id')
            ->whereNotNull('matched_outbound_id')
            ->distinct()
            ->pluck('matched_opportunity_id');

        if ($opportunityIds->isEmpty()) {
            $this->info('No replied opportunities found — nothing to cancel.');
            return self::SUCCESS;
        }

        $cancelled = FollowUp::query()
            ->whereIn('opportunity_id', $opportunityIds)
            ->where('status', 'pending')
            ->update([
                'status'        => 'cancelled',
                'cancel_reason' => 'reply_received',
            ]);

        if ($cancelled > 0) {
            Log::info('crm:cancel-followups-on-reply: cancelled pending follow-ups', [
                'count'           => $cancelled,
                'opportunity_ids' => $opportunityIds->toArray(),
            ]);
        }

        $this->info("Cancelled {$cancelled} pending follow-up(s) for replied opportunities.");

        return self::SUCCESS;
    }
}

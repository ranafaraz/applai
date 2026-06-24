<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Direct repair for opportunity #484 (Mursion "VP of AI & Machine Learning").
 *
 * The previous backfill migration (200004) updated inbox_messages.received_at
 * but the timeline_events update may have been skipped due to timelineable_type
 * morph-map differences or window mismatch. This migration targets the row
 * directly by opportunity ID without any time-window or from_email guard.
 *
 * Correct UTC arrival time (confirmed via Gmail headers): 2026-06-24 13:58:11
 */
return new class extends Migration
{
    private const OPPORTUNITY_ID   = 484;
    private const CORRECT_UTC_TIME = '2026-06-24 13:58:11';

    public function up(): void
    {
        // Fix timeline_events — try all possible Eloquent morph representations
        $typeCandidates = [
            'App\\Models\\Opportunity',
            'opportunity',
        ];

        $timelineFixed = DB::table('timeline_events')
            ->whereIn('timelineable_type', $typeCandidates)
            ->where('timelineable_id', self::OPPORTUNITY_ID)
            ->where('event_type', 'reply_received')
            ->update(['happened_at' => self::CORRECT_UTC_TIME]);

        Log::info('Migration 200005: timeline_events rows fixed', ['count' => $timelineFixed]);

        // Fix inbox_messages for the same day (broader match, no time-window)
        $inboxFixed = DB::table('inbox_messages')
            ->where('from_email', 'careers+noreply@mursion.com')
            ->whereDate('received_at', '2026-06-24')
            ->whereRaw("TIME(received_at) < '12:00:00'")  // only fix timestamps clearly before noon UTC (still PKT-wrong)
            ->update(['received_at' => self::CORRECT_UTC_TIME]);

        Log::info('Migration 200005: inbox_messages rows fixed', ['count' => $inboxFixed]);
    }

    public function down(): void
    {
        // Restore the (wrong) stored value so migration is fully reversible
        $wrongValue = '2026-06-24 08:58:11';

        DB::table('timeline_events')
            ->whereIn('timelineable_type', ['App\\Models\\Opportunity', 'opportunity'])
            ->where('timelineable_id', self::OPPORTUNITY_ID)
            ->where('event_type', 'reply_received')
            ->where('happened_at', self::CORRECT_UTC_TIME)
            ->update(['happened_at' => $wrongValue]);

        DB::table('inbox_messages')
            ->where('from_email', 'careers+noreply@mursion.com')
            ->where('received_at', self::CORRECT_UTC_TIME)
            ->update(['received_at' => $wrongValue]);
    }
};

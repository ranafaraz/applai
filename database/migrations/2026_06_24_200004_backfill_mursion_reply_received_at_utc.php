<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-off correction for inbox_messages whose received_at was stored with the
 * local-time digits from the email Date header instead of the UTC-converted
 * value (ImapSyncService::parseDate() was missing ->utc()).
 *
 * Specific case confirmed by the user:
 *   Opportunity #484 "VP of AI and Machine Learning" (Mursion, Inc.)
 *   From: careers+noreply@mursion.com
 *   True arrival (per Gmail headers): 2026-06-24 13:58:11 UTC
 *   Was stored as: ~2026-06-24 08:58:11 UTC  (US Central time, UTC-5)
 *
 * The underlying bug in parseDate() is fixed in the same deploy.
 * This migration repairs already-persisted wrong values.
 *
 * The migration is reversible (down() restores the old wrong values).
 */
return new class extends Migration
{
    /** The known-correct UTC arrival time for the Mursion auto-reply. */
    private const CORRECT_RECEIVED_AT = '2026-06-24 13:58:11';

    /** Window around the wrong stored time to find the row (±2 h). */
    private const WINDOW_START = '2026-06-24 06:00:00';
    private const WINDOW_END   = '2026-06-24 11:00:00';

    public function up(): void
    {
        $rows = DB::table('inbox_messages')
            ->where('from_email', 'careers+noreply@mursion.com')
            ->whereBetween('received_at', [self::WINDOW_START, self::WINDOW_END])
            ->get(['id', 'received_at', 'matched_opportunity_id']);

        if ($rows->isEmpty()) {
            Log::info('Backfill migration: no matching Mursion inbox_messages found — nothing to update.');
            return;
        }

        foreach ($rows as $row) {
            $old = $row->received_at;

            DB::table('inbox_messages')
                ->where('id', $row->id)
                ->update(['received_at' => self::CORRECT_RECEIVED_AT]);

            // Update the matching timeline event so the opportunity timeline
            // shows the correct sort position.
            $updated = DB::table('timeline_events')
                ->where('event_type', 'reply_received')
                ->whereBetween('happened_at', [self::WINDOW_START, self::WINDOW_END])
                ->when($row->matched_opportunity_id, function ($q) use ($row) {
                    $q->where(function ($inner) use ($row) {
                        $inner->where('timelineable_type', 'App\\Models\\Opportunity')
                              ->where('timelineable_id', $row->matched_opportunity_id);
                    })->orWhere(function ($inner) use ($row) {
                        // Also match directly on the InboxMessage record
                        $inner->where('timelineable_type', 'App\\Models\\InboxMessage')
                              ->where('timelineable_id', $row->id);
                    });
                })
                ->update(['happened_at' => self::CORRECT_RECEIVED_AT]);

            Log::info('Backfill migration: corrected Mursion reply timestamps', [
                'inbox_message_id'  => $row->id,
                'old_received_at'   => $old,
                'new_received_at'   => self::CORRECT_RECEIVED_AT,
                'timeline_rows_updated' => $updated,
            ]);
        }
    }

    public function down(): void
    {
        // Reverse: put the (wrong) old values back so this migration is rollback-safe.
        // The old stored value was ~08:58 (US Central Date header digits stored as UTC).
        // Using 08:58:11 as the best approximation.
        $oldValue = '2026-06-24 08:58:11';

        DB::table('inbox_messages')
            ->where('from_email', 'careers+noreply@mursion.com')
            ->where('received_at', self::CORRECT_RECEIVED_AT)
            ->update(['received_at' => $oldValue]);

        DB::table('timeline_events')
            ->where('event_type', 'reply_received')
            ->where('happened_at', self::CORRECT_RECEIVED_AT)
            ->update(['happened_at' => $oldValue]);
    }
};

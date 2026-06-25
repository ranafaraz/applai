<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Robust, data-driven repair for reply timestamps that sort BEFORE the outbound
 * send they answer (opportunity #484 "VP of AI & Machine Learning" and any other
 * row hit by the same bug).
 *
 * ── Root cause ────────────────────────────────────────────────────────────
 * ImapSyncService::parseDate() formerly stored the email Date header's
 * LOCAL-TIME digits without applying its UTC offset. A Mursion auto-reply whose
 * header read "13:58:11 -0500" was therefore persisted as 13:58:11 instead of
 * the correct 18:58:11 UTC — landing ~5 h too early and sorting before the
 * outbound send it acknowledges. parseDate() now calls ->utc(), so newly
 * ingested mail is correct.
 *
 * ── Why the earlier one-off migrations did not fix it ─────────────────────
 * Migrations 200004 / 200005 assumed the stored (wrong) value was ~08:58 and
 * guarded their UPDATEs with a 06:00–11:00 / "TIME < 12:00" window. The value
 * was actually 13:58, so those guards never matched the inbox row, and 200005
 * hard-set the timeline event to 13:58:11 — which is still the pre-offset value
 * and still sorts before the 18:58 send. Hardcoding an absolute timestamp from
 * a mistaken mental model is exactly what made the bug survive.
 *
 * ── This fix ──────────────────────────────────────────────────────────────
 * No magic timestamp. A reply that is matched to an outbound send physically
 * cannot have arrived before that send, so received_at < sent_at is always a
 * corruption. For every such row we reset the reply's timestamp to the send
 * instant (auto-acknowledgements are emitted within seconds, so this is both
 * accurate and guaranteed to order correctly). The reply_received timeline
 * events share that timestamp; the id-desc tiebreak in TimelineService keeps
 * the reply directly above the send. General and idempotent — re-running finds
 * nothing left to fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Replies matched to an outbound send whose stored arrival predates it.
        $rows = DB::table('inbox_messages as im')
            ->join('email_messages as em', 'em.id', '=', 'im.matched_outbound_id')
            ->whereNotNull('im.matched_outbound_id')
            ->whereNotNull('em.sent_at')
            ->whereColumn('im.received_at', '<', 'em.sent_at')
            ->select('im.id', 'im.received_at as old_received_at', 'em.sent_at')
            ->get();

        foreach ($rows as $row) {
            DB::table('inbox_messages')
                ->where('id', $row->id)
                ->update(['received_at' => $row->sent_at]);

            // Realign the reply_received timeline events (opportunity + contact)
            // created from this inbox message.
            $timelineFixed = DB::table('timeline_events')
                ->where('event_type', 'reply_received')
                ->where('metadata->inbox_message_id', $row->id)
                ->update(['happened_at' => $row->sent_at]);

            Log::info('Migration 100000: repaired reply timestamp that predated its send', [
                'inbox_message_id'   => $row->id,
                'old_received_at'    => $row->old_received_at,
                'new_received_at'    => $row->sent_at,
                'timeline_rows_fixed'=> $timelineFixed,
            ]);
        }

        Log::info('Migration 100000: reply-before-send repair complete', [
            'inbox_messages_fixed' => $rows->count(),
        ]);

        // Second pass — purely timeline-based, independent of the inbox/outbound
        // matching columns. Catches #484 even if its inbox row lost its
        // matched_outbound_id link. The only physically-impossible ordering is a
        // reply that predates the FIRST outreach on the opportunity (you cannot
        // receive a reply before you have sent anything). A reply that sits
        // between a first send and a later follow-up is legitimate and is left
        // untouched. Any reply before the first send is lifted to that send.
        $replies = DB::table('timeline_events')
            ->where('event_type', 'reply_received')
            ->where('timelineable_type', 'App\\Models\\Opportunity')
            ->get(['id', 'timelineable_id', 'happened_at']);

        foreach ($replies as $reply) {
            $firstSendAt = DB::table('timeline_events')
                ->where('event_type', 'email_sent')
                ->where('timelineable_type', 'App\\Models\\Opportunity')
                ->where('timelineable_id', $reply->timelineable_id)
                ->min('happened_at');

            if ($firstSendAt && $reply->happened_at < $firstSendAt) {
                DB::table('timeline_events')
                    ->where('id', $reply->id)
                    ->update(['happened_at' => $firstSendAt]);

                Log::info('Migration 100000: lifted reply_received above the first send (timeline pass)', [
                    'timeline_event_id' => $reply->id,
                    'opportunity_id'    => $reply->timelineable_id,
                    'old_happened_at'   => $reply->happened_at,
                    'new_happened_at'   => $firstSendAt,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No-op: the original values were logically impossible (a reply before
        // its own send) and are not worth — nor safely possible to — restore.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('timeline_events')
            ->whereIn('event_type', ['email_sent', 'email_failed'])
            ->orderBy('id')
            ->get(['id', 'timelineable_id', 'timelineable_type', 'event_type', 'description', 'metadata']);

        $groups = [];

        foreach ($rows as $row) {
            $metadata = json_decode((string) $row->metadata, true);
            $emailMessageId = $metadata['email_message_id'] ?? null;

            if (! $emailMessageId) {
                continue;
            }

            $key = implode('|', [
                $row->timelineable_type,
                $row->timelineable_id,
                $row->event_type,
                $emailMessageId,
            ]);

            $groups[$key][] = $row;
        }

        $deleteIds = [];

        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, function ($a, $b) {
                $length = strlen((string) $b->description) <=> strlen((string) $a->description);

                return $length !== 0 ? $length : ($a->id <=> $b->id);
            });

            array_shift($group);
            foreach ($group as $duplicate) {
                $deleteIds[] = $duplicate->id;
            }
        }

        foreach (array_chunk($deleteIds, 500) as $chunk) {
            DB::table('timeline_events')->whereIn('id', $chunk)->delete();
        }
    }

    public function down(): void
    {
        //
    }
};

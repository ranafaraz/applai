<?php

namespace App\Services;

use App\Models\TimelineEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TimelineService
{
    /**
     * Create a timeline event attached to any timelineable model.
     *
     * @param  Model       $model       The Eloquent model (must have a morphMany timelineEvents relation).
     * @param  string      $eventType   Slug identifying the event type (e.g. 'email_sent').
     * @param  string      $description Human-readable description.
     * @param  array       $metadata    Optional JSON payload stored alongside the event.
     * @param  Carbon|null $happenedAt  When the event occurred; defaults to now().
     */
    public function log(
        Model $model,
        string $eventType,
        string $description,
        array $metadata = [],
        ?Carbon $happenedAt = null,
    ): TimelineEvent {
        /** @var int $userId Resolve the user_id from the model or fall back gracefully. */
        $userId = $model->user_id ?? null;

        return TimelineEvent::create([
            'user_id'            => $userId,
            'timelineable_id'    => $model->getKey(),
            'timelineable_type'  => $model->getMorphClass(),
            'event_type'         => $eventType,
            'description'        => $description,
            'metadata'           => $metadata,
            'happened_at'        => $happenedAt ?? now(),
        ]);
    }

    /**
     * Return all timeline events for a model, ordered from newest to oldest.
     *
     * @param  Model $model
     * @return Collection<int, TimelineEvent>
     */
    public function getTimelineFor(Model $model): Collection
    {
        return TimelineEvent::query()
            ->where('timelineable_type', $model->getMorphClass())
            ->where('timelineable_id', $model->getKey())
            ->orderByDesc('happened_at')
            ->orderByDesc('id')
            ->get();
    }
}

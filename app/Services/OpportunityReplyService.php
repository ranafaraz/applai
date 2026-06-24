<?php

namespace App\Services;

use App\Models\InboxMessage;
use Carbon\Carbon;

class OpportunityReplyService
{
    /**
     * Check whether any reply has been received for the given opportunity,
     * optionally restricting to replies that arrived after $since.
     */
    public function hasReplySince(int $opportunityId, ?Carbon $since = null): bool
    {
        $query = InboxMessage::query()
            ->where('matched_opportunity_id', $opportunityId)
            ->whereNotNull('matched_outbound_id');

        if ($since !== null) {
            $query->where('received_at', '>=', $since);
        }

        return $query->exists();
    }
}

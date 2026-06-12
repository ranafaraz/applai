<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use Carbon\CarbonInterface;

/**
 * Single source of truth for moving an approved post (and its targets) into
 * the scheduled/approved state consumed by social:publish-due-posts.
 *
 * Both the web Social Studio flow and the GPT/API confirmation flow must go
 * through this service so the two can't drift apart (the API flow previously
 * approved schedule confirmations without ever producing a scheduled target,
 * so those posts never published).
 */
class SocialPostSchedulerService
{
    /**
     * Mark the post approved and mirror the schedule (or immediate approval)
     * onto all unpublished targets. When $scheduledAt is null the post's own
     * scheduled_at is used, matching the web approval flow.
     */
    public function applyApproval(SocialPost $post, int $approvedBy, ?CarbonInterface $scheduledAt = null, ?string $timezone = null): void
    {
        $scheduledAt ??= $post->scheduled_at;
        $status = $scheduledAt ? 'scheduled' : 'approved';

        $post->update([
            'approval_status'  => 'approved',
            'approved_at'      => now(),
            'approved_by'      => $approvedBy,
            'status'           => $status,
            'scheduled_at'     => $scheduledAt,
            'timezone_display' => $timezone ?? $post->timezone_display,
        ]);

        $post->targets()->whereNot('status', 'published')->update([
            'status'       => $status,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    /**
     * Return an unpublished target for the post, creating one against the
     * user's default connected LinkedIn account when none exists yet.
     */
    public function ensureLinkedInTarget(SocialPost $post, int $userId): ?SocialPostTarget
    {
        $existing = $post->targets()->whereNot('status', 'published')->first();
        if ($existing) {
            return $existing;
        }

        $account = SocialAccount::where('user_id', $userId)
            ->whereHas('provider', fn ($q) => $q->where('key', 'linkedin'))
            ->where('status', 'connected')
            ->orderByDesc('is_default')
            ->first();

        if (! $account) {
            return null;
        }

        return SocialPostTarget::create([
            'social_post_id'    => $post->id,
            'social_account_id' => $account->id,
            'provider_key'      => 'linkedin',
            'status'            => 'draft',
        ]);
    }
}

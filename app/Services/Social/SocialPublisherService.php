<?php

namespace App\Services\Social;

use App\Models\SocialPost;
use App\Models\SocialPostTarget;

class SocialPublisherService
{
    public function __construct(
        private LinkedInPublishService $linkedIn,
        private WordPressPublishService $wordPress,
    ) {}

    public function publish(SocialPostTarget $target): SocialPostTarget
    {
        $result = match ($target->provider_key) {
            'linkedin' => $this->linkedIn->publish($target),
            'wordpress' => $this->wordPress->publish($target),
            default => throw new \InvalidArgumentException("Unsupported social provider [{$target->provider_key}]."),
        };

        $this->refreshPostStatus($result->post);

        return $result;
    }

    private function refreshPostStatus(SocialPost $post): void
    {
        $statuses = $post->targets()->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        if ($statuses->every(fn (string $status) => $status === 'published')) {
            $post->update(['status' => 'published']);
            return;
        }

        if ($statuses->contains('publishing')) {
            $post->update(['status' => 'publishing']);
            return;
        }

        if ($statuses->contains('scheduled')) {
            $post->update(['status' => 'scheduled']);
            return;
        }

        if ($statuses->contains('failed')) {
            $post->update(['status' => 'failed']);
        }
    }
}

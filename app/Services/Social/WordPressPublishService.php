<?php

namespace App\Services\Social;

use App\Models\SocialActivityLog;
use App\Models\SocialMediaAsset;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\SocialPublishJob;
use Illuminate\Support\Facades\Log;

class WordPressPublishService
{
    public function __construct(private WordPressClient $client) {}

    public function publish(SocialPostTarget $target): SocialPostTarget
    {
        $target->loadMissing(['post.mediaAssets', 'account']);

        $post = $target->post;
        $account = $target->account;
        $this->guardPublish($target);

        $job = SocialPublishJob::create([
            'social_post_target_id' => $target->id,
            'scheduled_at' => now(),
            'job_status' => 'processing',
            'attempt_count' => $target->publishJobs()->count() + 1,
            'max_attempts' => 3,
        ]);

        $target->update(['status' => 'publishing']);
        $post->update(['status' => 'publishing']);

        try {
            $metadata = $target->platform_metadata_json ?? [];
            $content = $this->replaceInlineImages($account, $post, $target->platform_body ?: $post->post_body);
            $featuredMediaId = $this->resolveFeaturedMediaId($account, $post, $metadata);

            $payload = array_filter([
                'title' => $metadata['title'] ?? $post->title_internal,
                'content' => $content,
                'excerpt' => $metadata['excerpt'] ?? null,
                'slug' => $metadata['slug'] ?? null,
                'status' => $metadata['wp_status'] ?? 'draft',
                'featured_media' => $featuredMediaId,
            ], fn ($value) => $value !== null && $value !== '');

            $response = $this->client->createPost($account, $payload);
            $remoteId = (string) ($response['id'] ?? '');
            $remoteUrl = $response['link'] ?? null;

            $target->update([
                'status' => 'published',
                'remote_post_id' => $remoteId !== '' ? $remoteId : 'unknown',
                'remote_post_url' => $remoteUrl,
                'published_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            $job->update([
                'job_status' => 'succeeded',
                'provider_response_sanitized_json' => [
                    'id' => $remoteId,
                    'link' => $remoteUrl,
                    'status' => $response['status'] ?? null,
                ],
            ]);

            SocialActivityLog::record(
                $post->user_id,
                $post->tenant_id,
                'wordpress_published',
                SocialPost::class,
                $post->id,
                "Published to WordPress site {$account->display_name}"
            );
        } catch (\Throwable $e) {
            $message = $this->sanitizeError($e->getMessage());
            $isPermanent = $e instanceof WordPressPermanentException || $e instanceof WordPressAuthException;

            $target->update([
                'status' => 'failed',
                'error_code' => (string) $e->getCode(),
                'error_message' => $message,
            ]);

            $job->update([
                'job_status' => $isPermanent ? 'failed' : 'retrying',
                'next_retry_at' => (! $isPermanent && $job->attempt_count < $job->max_attempts)
                    ? now()->addMinutes((int) pow(2, $job->attempt_count) * 5)
                    : null,
                'provider_response_sanitized_json' => ['error' => $message],
            ]);

            if ($e instanceof WordPressAuthException) {
                $account->update(['status' => 'reauthorization_required']);
            }

            SocialActivityLog::record(
                $post->user_id,
                $post->tenant_id,
                'wordpress_publish_failed',
                SocialPost::class,
                $post->id,
                "WordPress publish failed: {$message}"
            );

            Log::error('WordPress publish failed', [
                'target_id' => $target->id,
                'post_id' => $post->id,
                'error' => $message,
            ]);
        }

        return $target->fresh();
    }

    private function guardPublish(SocialPostTarget $target): void
    {
        if ($target->isPublished()) {
            throw new WordPressPermanentException('Post has already been published.');
        }

        if (! $target->account || ! $target->account->isConnected()) {
            throw new WordPressPermanentException('WordPress site is not connected.');
        }

        if ($target->post->approval_status !== 'approved') {
            throw new WordPressPermanentException('Post has not been approved.');
        }
    }

    private function replaceInlineImages($account, SocialPost $post, string $content): string
    {
        return preg_replace_callback('/<img\b[^>]*data-social-asset-id=["\'](\d+)["\'][^>]*>/i', function (array $match) use ($account, $post) {
            $asset = SocialMediaAsset::where('user_id', $post->user_id)->find((int) $match[1]);
            if (! $asset) {
                return $match[0];
            }

            $media = $this->client->uploadMedia($account, $asset);
            $sourceUrl = $media['source_url'] ?? null;

            if (! $sourceUrl) {
                return $match[0];
            }

            $tag = preg_replace('/\s+src=["\'][^"\']*["\']/i', '', $match[0]) ?? $match[0];
            return preg_replace('/<img\b/i', '<img src="' . e($sourceUrl) . '"', $tag, 1) ?? $match[0];
        }, $content) ?? $content;
    }

    private function resolveFeaturedMediaId($account, SocialPost $post, array $metadata): ?int
    {
        $assetId = $metadata['featured_asset_id'] ?? null;
        $asset = null;

        if ($assetId) {
            $asset = SocialMediaAsset::where('user_id', $post->user_id)->find((int) $assetId);
        }

        if (! $asset) {
            $asset = $post->mediaAssets->first(fn (SocialMediaAsset $media) => (bool) $media->pivot?->is_featured);
        }

        if (! $asset) {
            return null;
        }

        $media = $this->client->uploadMedia($account, $asset);

        return isset($media['id']) ? (int) $media['id'] : null;
    }

    private function sanitizeError(string $message): string
    {
        return preg_replace('/Basic\s+[A-Za-z0-9+\/=]+/i', 'Basic [REDACTED]', $message) ?? $message;
    }
}

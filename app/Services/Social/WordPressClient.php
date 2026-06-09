<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialMediaAsset;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class WordPressClient
{
    public function verify(SocialAccount $account): array
    {
        $response = $this->request($account)->get($this->endpoint($account, 'users/me'));

        $this->assertSuccess($response, 'WordPress verify connection');

        return $response->json();
    }

    public function uploadMedia(SocialAccount $account, SocialMediaAsset $asset): array
    {
        $path = Storage::disk('public')->path($asset->storage_path);

        if (! is_file($path)) {
            throw new WordPressPermanentException('Media file is missing from storage.');
        }

        $response = $this->request($account)
            ->attach('file', file_get_contents($path), $asset->filename, [
                'Content-Type' => $asset->mime_type ?: 'application/octet-stream',
            ])
            ->post($this->endpoint($account, 'media'), [
                'alt_text' => $asset->alt_text,
                'caption' => $asset->caption_or_prompt_note,
            ]);

        $this->assertSuccess($response, 'WordPress upload media');

        return $response->json();
    }

    public function createPost(SocialAccount $account, array $payload): array
    {
        $response = $this->request($account)
            ->asJson()
            ->post($this->endpoint($account, 'posts'), $payload);

        $this->assertSuccess($response, 'WordPress create post');

        return $response->json();
    }

    public function endpoint(SocialAccount $account, string $path): string
    {
        $base = rtrim($account->metadata_json['api_base'] ?? '', '/');

        if ($base === '') {
            $siteUrl = rtrim((string) $account->provider_account_urn, '/');
            $base = "{$siteUrl}/wp-json/wp/v2";
        }

        return $base . '/' . ltrim($path, '/');
    }

    private function request(SocialAccount $account): \Illuminate\Http\Client\PendingRequest
    {
        $username = $account->metadata_json['username'] ?? null;
        $password = $account->access_token_encrypted;

        if (! $username || ! $password) {
            throw new WordPressAuthException('WordPress username or application password is missing.');
        }

        return Http::timeout(30)
            ->acceptJson()
            ->withBasicAuth($username, $password);
    }

    private function assertSuccess(Response $response, string $context): void
    {
        if ($response->successful()) {
            return;
        }

        $body = $this->sanitize($response->body());
        $status = $response->status();

        if (in_array($status, [401, 403], true)) {
            throw new WordPressAuthException("{$context} failed ({$status}): {$body}");
        }

        if (in_array($status, [400, 404, 409, 422], true)) {
            throw new WordPressPermanentException("{$context} failed ({$status}): {$body}");
        }

        throw new \RuntimeException("{$context} failed ({$status}): {$body}");
    }

    private function sanitize(string $text): string
    {
        return preg_replace('/Authorization:\s*Basic\s+[A-Za-z0-9+\/=]+/i', 'Authorization: Basic [REDACTED]', $text) ?? $text;
    }
}

class WordPressAuthException extends \RuntimeException {}
class WordPressPermanentException extends \RuntimeException {}

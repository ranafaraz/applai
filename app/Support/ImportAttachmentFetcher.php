<?php

namespace App\Support;

use App\Models\Document;
use App\Models\EmailAttachment;
use App\Models\EmailMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches public URLs listed in CSV import columns (draft_attachments,
 * followup_attachments) and exposes the downloaded files for linking to
 * EmailMessage / Document rows.
 *
 * Security posture:
 *   - http/https only (no file://, ftp://, etc.)
 *   - 20 MB per-file size cap (matches the compose upload cap)
 *   - 20 s connect/read timeout so a slow URL can't stall an import
 *   - exceptions caught + logged; failed downloads are silently skipped
 *     so a single bad URL doesn't kill the whole row
 */
class ImportAttachmentFetcher
{
    private const MAX_BYTES = 20 * 1024 * 1024;
    private const TIMEOUT   = 20;

    /**
     * Download every URL in the (semicolon or comma separated) list and
     * return descriptors that can later be attached to an EmailMessage.
     *
     * @return array<int, array{path:string, name:string, mime:string, size:int}>
     */
    public static function fetchAll(?string $raw): array
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') return [];

        $dir = storage_path('app/private/email-attachments');
        if (! is_dir($dir)) mkdir($dir, 0775, true);

        $urls = preg_split('/[;,\s]+/', $raw) ?: [];
        $files = [];

        foreach ($urls as $url) {
            $url = trim($url);
            if ($url === '') continue;
            if (! preg_match('#^https?://#i', $url)) {
                Log::info('ImportAttachmentFetcher: skipping non-http(s) URL', ['url' => $url]);
                continue;
            }

            try {
                $res = Http::withOptions(['stream' => false])
                    ->timeout(self::TIMEOUT)
                    ->withHeaders(['User-Agent' => 'PersonalOutreachCRM-Importer/1.0'])
                    ->get($url);

                if (! $res->successful()) {
                    Log::info('ImportAttachmentFetcher: download non-2xx', ['url' => $url, 'status' => $res->status()]);
                    continue;
                }

                $body = $res->body();
                if (strlen($body) === 0 || strlen($body) > self::MAX_BYTES) {
                    Log::info('ImportAttachmentFetcher: empty or too large', ['url' => $url, 'bytes' => strlen($body)]);
                    continue;
                }

                $name = self::guessFilename($url, $res->header('Content-Disposition'));
                $safe = time() . '_' . random_int(1000, 9999) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
                $full = $dir . '/' . $safe;
                file_put_contents($full, $body);

                $files[] = [
                    'path' => 'email-attachments/' . $safe,
                    'name' => $name,
                    'mime' => $res->header('Content-Type') ?: 'application/octet-stream',
                    'size' => strlen($body),
                ];
            } catch (Throwable $e) {
                Log::warning('ImportAttachmentFetcher: download failed', [
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $files;
    }

    /**
     * Link each pre-fetched file to the given EmailMessage as an
     * EmailAttachment AND mirror into the central Documents library.
     *
     * @param  array<int, array{path:string, name:string, mime:string, size:int}>  $files
     */
    public static function attachToMessage(EmailMessage $message, array $files, int $userId, ?int $tenantId): void
    {
        foreach ($files as $f) {
            $documentId = null;
            try {
                $doc = Document::create([
                    'tenant_id'      => $tenantId,
                    'user_id'        => $userId,
                    'opportunity_id' => $message->opportunity_id,
                    'contact_id'     => $message->contact_id,
                    'name'           => $f['name'],
                    'file_path'      => $f['path'],
                    'file_name'      => $f['name'],
                    'file_size'      => $f['size'],
                    'mime_type'      => $f['mime'],
                    'document_type'  => 'email_attachment',
                ]);
                $documentId = $doc->id;
            } catch (Throwable $e) {
                Log::warning('ImportAttachmentFetcher: Document create failed', ['error' => $e->getMessage()]);
            }

            try {
                EmailAttachment::create([
                    'email_message_id' => $message->id,
                    'document_id'      => $documentId,
                    'file_name'        => $f['name'],
                    'file_path'        => $f['path'],
                    'mime_type'        => $f['mime'],
                    'file_size'        => $f['size'],
                ]);
            } catch (Throwable $e) {
                Log::warning('ImportAttachmentFetcher: EmailAttachment create failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Pick a filename: prefer Content-Disposition filename=, else the last
     * URL path segment, else fall back to "attachment".
     */
    private static function guessFilename(string $url, ?string $disposition): string
    {
        if ($disposition && preg_match('/filename\*?=(?:UTF-8\'\'|")?([^";]+)/i', $disposition, $m)) {
            $name = trim($m[1], '"');
            if ($name !== '') return $name;
        }
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $base = basename($path);
        return $base !== '' ? $base : 'attachment';
    }
}

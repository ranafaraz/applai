<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiDocumentVersion extends Model
{
    protected $table = 'api_document_versions';

    // Versions are immutable — no updated_at column.
    const UPDATED_AT = null;

    protected $fillable = [
        'api_document_id',
        'version_number',
        'original_filename',
        'mime_type',
        'size_bytes',
        'checksum',
        'storage_path',
        'public_url',
        'content_format',
        'content_body',
        'upload_source',
        'version_notes',
        'uploaded_by_api_client_id',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes'     => 'integer',
            'version_number' => 'integer',
        ];
    }

    const UPLOAD_SOURCES = ['multipart', 'url', 'agent', 'remote_fetch', 'inline_content'];

    const CONTENT_FORMATS = ['html', 'markdown', 'plaintext'];

    /** content_format => the mime type stored on the version. */
    const CONTENT_MIME_TYPES = [
        'html'      => 'text/html',
        'markdown'  => 'text/markdown',
        'plaintext' => 'text/plain',
    ];

    const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    // Filename substrings that flag a document as sensitive.
    const SENSITIVE_PATTERNS = [
        'passport', 'cnic', 'nic', 'national_id', 'national-id',
        'id_card', 'id-card', 'transcript', 'degree', 'diploma', 'certificate',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function document(): BelongsTo
    {
        return $this->belongsTo(ApiDocument::class, 'api_document_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** True when this version holds inline authored text (not a file or URL). */
    public function isContentDoc(): bool
    {
        return $this->upload_source === 'inline_content' || $this->content_body !== null;
    }

    /** First ~$len characters of the content as plain text (for list previews). */
    public function contentPreview(int $len = 200): ?string
    {
        if (! $this->isContentDoc()) {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags((string) $this->content_body), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
    }

    public static function detectSensitiveWarnings(string $filename, string $documentType): array
    {
        $warnings = [];
        $lower    = strtolower($filename);

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                $warnings[] = "Filename suggests identity or academic credentials ({$pattern}). Confirm recipient consent before sending.";
                break;
            }
        }

        if (in_array($documentType, ['resume', 'cover_letter'], true)) {
            $warnings[] = 'Personal career document — verify the correct recipient before attaching to cold outreach.';
        }

        return $warnings;
    }
}

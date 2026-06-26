<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class EmailSignature extends Model
{
    use SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'body',
        'image_path',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** Sanitize rich-text HTML from the WYSIWYG editor before persisting. */
    protected function body(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => RichText::sanitizeForStorage($value));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function renderHtml(): string
    {
        $body = self::tightenSpacing(trim((string) $this->body));
        $image = $this->image_url
            ? '<div style="margin-top:6px"><img src="' . e($this->image_url) . '" alt="' . e($this->name) . '" style="max-width:220px;height:auto;"></div>'
            : '';

        return '<div><br></div><div data-email-signature="1" data-email-signature-id="' . e((string) $this->id) . '">' . $body . $image . '</div>';
    }

    public static function stripSignatureHtml(?string $html): string
    {
        // Match the signature block (with or without the leading spacer div) to
        // the end of the string, so old and new rendered formats both strip.
        return preg_replace('/\s*(?:<div><br><\/div>\s*)?<div[^>]*\bdata-email-signature="1"[^>]*>.*$/is', '', (string) $html) ?? (string) $html;
    }

    /**
     * Collapse the default paragraph margins that make signatures render with
     * big, unprofessional gaps between lines in email clients. Inline styles
     * are required because email clients ignore <style> blocks.
     */
    private static function tightenSpacing(string $html): string
    {
        return preg_replace_callback('/<p\b([^>]*)>/i', function (array $m): string {
            $attrs = $m[1];
            if (preg_match('/style\s*=\s*"([^"]*)"/i', $attrs, $sm)) {
                $style = 'margin:0 0 2px;' . rtrim(trim($sm[1]), ';');
                $attrs = preg_replace('/style\s*=\s*"[^"]*"/i', 'style="' . $style . '"', $attrs);
            } else {
                $attrs .= ' style="margin:0 0 2px"';
            }
            return '<p' . $attrs . '>';
        }, $html) ?? $html;
    }
}

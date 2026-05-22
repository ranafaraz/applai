<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    protected $fillable = [
        'email_message_id',
        'document_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function emailMessage(): BelongsTo
    {
        return $this->belongsTo(EmailMessage::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}

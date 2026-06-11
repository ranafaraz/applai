<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxAttachment extends Model
{
    protected $fillable = [
        'inbox_message_id',
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

    public function inboxMessage(): BelongsTo
    {
        return $this->belongsTo(InboxMessage::class);
    }
}

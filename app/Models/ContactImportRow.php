<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactImportRow extends Model
{
    protected $fillable = [
        'contact_import_id',
        'row_number',
        'raw_data',
        'status',
        'error_message',
        'contact_id',
    ];

    protected function casts(): array
    {
        return [
            'raw_data'   => 'array',
            'row_number' => 'integer',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function contactImport(): BelongsTo
    {
        return $this->belongsTo(ContactImport::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}

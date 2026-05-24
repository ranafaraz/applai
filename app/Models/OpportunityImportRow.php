<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityImportRow extends Model
{
    protected $fillable = [
        'opportunity_import_id',
        'row_number',
        'raw_data',
        'status',
        'error_message',
        'opportunity_id',
    ];

    protected function casts(): array
    {
        return [
            'raw_data'   => 'array',
            'row_number' => 'integer',
        ];
    }

    public function opportunityImport(): BelongsTo
    {
        return $this->belongsTo(OpportunityImport::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }
}

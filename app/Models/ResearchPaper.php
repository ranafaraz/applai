<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResearchPaper extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'authors',
        'abstract',
        'url',
        'pdf_url',
        'arxiv_id',
        'doi',
        'venue',
        'published_date',
        'status',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'authors'        => 'array',
            'meta'           => 'array',
            'published_date' => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeToRead($query)
    {
        return $query->where('status', 'to_read');
    }
}

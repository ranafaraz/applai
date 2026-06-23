<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentItem extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'content_type',
        'channel',
        'status',
        'body',
        'notes',
        'scheduled_for',
        'published_at',
        'published_url',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'published_at'  => 'datetime',
            'meta'          => 'array',
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

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}

<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class YoutubeVideo extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'video_id',
        'url',
        'description',
        'status',
        'visibility',
        'channel',
        'thumbnail_url',
        'duration_seconds',
        'tags',
        'view_count',
        'like_count',
        'comment_count',
        'scheduled_for',
        'published_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'tags'             => 'array',
            'view_count'       => 'integer',
            'like_count'       => 'integer',
            'comment_count'    => 'integer',
            'scheduled_for'    => 'datetime',
            'published_at'     => 'datetime',
            'meta'             => 'array',
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

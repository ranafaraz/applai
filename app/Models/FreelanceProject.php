<?php

namespace App\Models;

use App\Models\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FreelanceProject extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'contact_id',
        'opportunity_id',
        'title',
        'client_name',
        'platform',
        'status',
        'rate_type',
        'rate',
        'budget',
        'currency',
        'estimated_hours',
        'hours_logged',
        'description',
        'url',
        'start_date',
        'due_date',
        'completed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'rate'            => 'decimal:2',
            'budget'          => 'decimal:2',
            'estimated_hours' => 'decimal:2',
            'hours_logged'    => 'decimal:2',
            'start_date'      => 'date',
            'due_date'        => 'date',
            'completed_at'    => 'datetime',
            'meta'            => 'array',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'email', 'plan', 'status',
        'max_users', 'trial_ends_at', 'notes',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'admin');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trial']);
    }

    public function isTrial(): bool
    {
        return $this->status === 'trial';
    }

    public function trialExpired(): bool
    {
        return $this->isTrial()
            && $this->trial_ends_at
            && $this->trial_ends_at->isPast();
    }

    public function planLabel(): string
    {
        return match ($this->plan) {
            'pro'        => 'Pro',
            'enterprise' => 'Enterprise',
            default      => 'Free',
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'active'    => 'bg-green-100 text-green-800',
            'trial'     => 'bg-yellow-100 text-yellow-800',
            'suspended' => 'bg-red-100 text-red-800',
            'cancelled' => 'bg-gray-100 text-gray-600',
            default     => 'bg-gray-100 text-gray-600',
        };
    }
}

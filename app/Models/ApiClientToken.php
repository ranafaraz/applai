<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiClientToken extends Model
{
    protected $fillable = [
        'api_client_id',
        'user_id',
        'name',
        'token_hash',
        'token_prefix',
        'is_active',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new raw token, return it, and populate hash/prefix on this model.
     * Caller must save() the model and store the raw token securely (shown once).
     */
    public static function generateRaw(string $env = 'live'): array
    {
        $raw    = 'pocrm_' . $env . '_' . Str::random(40);
        $hash   = hash('sha256', $raw);
        $prefix = substr($raw, 0, 16);

        return compact('raw', 'hash', 'prefix');
    }

    public static function findByRaw(string $raw): ?self
    {
        $hash = hash('sha256', $raw);
        return static::where('token_hash', $hash)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

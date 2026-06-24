<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Opaque, long-lived refresh token for the mobile API. Hashed at rest; the raw
 * string is returned to the client only once (at issuance / rotation).
 */
class RefreshToken extends Model
{
    protected $table = 'app_refresh_tokens';

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'revoked_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Issue a fresh refresh token for the user; returns the raw (unhashed) value. */
    public static function issueFor(User $user, int $days = 30): string
    {
        $raw = Str::random(64);

        static::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addDays($days),
        ]);

        return $raw;
    }

    /** Find a usable (unrevoked, unexpired) token by its raw value, or null. */
    public static function findValid(string $raw): ?self
    {
        return static::where('token_hash', hash('sha256', $raw))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }
}

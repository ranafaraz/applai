<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuppressionList extends Model
{
    use HasFactory;

    protected $table = 'suppression_list';

    protected $fillable = [
        'user_id',
        'email',
        'reason',
        'notes',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether an email address is on the suppression list for a given user.
     */
    public static function isSuppressed(int $userId, string $email): bool
    {
        return static::where('user_id', $userId)
                     ->where('email', strtolower(trim($email)))
                     ->exists();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $fillable = [
        'user_id',
        'timezone',
        'date_format',
        'default_follow_up_days',
        'default_email_account_id',
        'notify_on_reply',
        'notify_on_bounce',
        'onboarding_dismissed_at',
        'openai_api_key',
        'openai_model',
    ];

    protected $hidden = ['openai_api_key'];

    protected function casts(): array
    {
        return [
            'default_follow_up_days'  => 'integer',
            'notify_on_reply'         => 'boolean',
            'notify_on_bounce'        => 'boolean',
            'onboarding_dismissed_at' => 'datetime',
            'openai_api_key'          => 'encrypted',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function defaultEmailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'default_email_account_id');
    }
}

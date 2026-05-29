<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'api_client_id',
        'user_id',
        'endpoint',
        'method',
        'request_summary',
        'response_status',
        'ip_address',
        'user_agent',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'request_summary' => 'array',
            'created_at'      => 'datetime',
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
}

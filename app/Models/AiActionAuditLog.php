<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'api_client_id',
        'source',
        'action',
        'entity_type',
        'entity_id',
        'risk_level',
        'input_summary',
        'output_summary',
        'status',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public static function record(
        int $userId,
        string $source,
        string $action,
        ?int $apiClientId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        string $riskLevel = 'low',
        ?string $inputSummary = null,
        ?string $outputSummary = null,
        string $status = 'success',
        ?string $ip = null,
    ): void {
        static::create([
            'user_id'        => $userId,
            'api_client_id'  => $apiClientId,
            'source'         => $source,
            'action'         => $action,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'risk_level'     => $riskLevel,
            'input_summary'  => $inputSummary,
            'output_summary' => $outputSummary,
            'status'         => $status,
            'ip_address'     => $ip,
            'created_at'     => now(),
        ]);
    }
}

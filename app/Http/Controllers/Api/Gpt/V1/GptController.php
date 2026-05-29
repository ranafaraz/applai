<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Http\Controllers\Controller;
use App\Models\AiActionAuditLog;
use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Http\Request;

abstract class GptController extends Controller
{
    protected function apiClient(Request $request): ApiClient
    {
        return $request->attributes->get('api_client');
    }

    protected function apiUser(Request $request): User
    {
        return $request->attributes->get('api_user');
    }

    protected function audit(
        Request $request,
        string $action,
        string $entityType = null,
        int $entityId = null,
        string $riskLevel = 'low',
        string $inputSummary = null,
        string $outputSummary = null,
        string $status = 'success',
    ): void {
        $client = $this->apiClient($request);
        AiActionAuditLog::record(
            userId:        $this->apiUser($request)->id,
            source:        $client->source_type,
            action:        $action,
            apiClientId:   $client->id,
            entityType:    $entityType,
            entityId:      $entityId,
            riskLevel:     $riskLevel,
            inputSummary:  $inputSummary,
            outputSummary: $outputSummary,
            status:        $status,
            ip:            $request->ip(),
        );
    }
}

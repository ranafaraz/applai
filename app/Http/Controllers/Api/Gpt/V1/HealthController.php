<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends GptController
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'version'   => 'v1',
            'timestamp' => now()->toISOString(),
        ]);
    }
}

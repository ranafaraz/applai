<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends GptController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user   = $this->apiUser($request);
        $client = $this->apiClient($request);

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'client' => [
                'id'          => $client->id,
                'name'        => $client->name,
                'source_type' => $client->source_type,
                'scopes'      => $client->scopes,
            ],
        ]);
    }
}

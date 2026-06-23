<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiClientScope
{
    public function handle(Request $request, Closure $next, string ...$requiredScopes): Response
    {
        $client = $request->attributes->get('api_client');

        if (! $client) {
            return response()->json([
                'success' => false,
                'error'   => 'Unauthenticated.',
                'message' => 'Unauthenticated.',
                'code'    => 'UNAUTHENTICATED',
            ], 401);
        }

        foreach ($requiredScopes as $scope) {
            if (! $client->hasScope($scope)) {
                return response()->json([
                    'success'          => false,
                    'error'            => 'Insufficient scope.',
                    'message'          => 'Insufficient scope.',
                    'code'             => 'FORBIDDEN',
                    'required_scope'   => $scope,
                    'available_scopes' => $client->scopes,
                ], 403);
            }
        }

        return $next($request);
    }
}

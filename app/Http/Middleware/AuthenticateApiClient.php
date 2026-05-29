<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\ApiClientToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->header('X-Api-Key');

        if (! $raw) {
            return response()->json(['error' => 'Missing X-Api-Key header.'], 401);
        }

        $token = ApiClientToken::findByRaw($raw);

        if (! $token) {
            return response()->json(['error' => 'Invalid API key.'], 401);
        }

        if (! $token->is_active) {
            return response()->json(['error' => 'API key is revoked.'], 401);
        }

        if ($token->isExpired()) {
            return response()->json(['error' => 'API key has expired.'], 401);
        }

        $client = $token->apiClient;

        if (! $client || ! $client->is_active) {
            return response()->json(['error' => 'API client is disabled.'], 401);
        }

        if ($client->isExpired()) {
            return response()->json(['error' => 'API client has expired.'], 401);
        }

        // IP allowlist check (if configured)
        if (! empty($client->allowed_ips)) {
            $ip = $request->ip();
            if (! in_array($ip, $client->allowed_ips, true)) {
                return response()->json(['error' => 'IP address not allowed.'], 403);
            }
        }

        // Stamp last_used_at
        $token->updateQuietly(['last_used_at' => now()]);
        $client->updateQuietly(['last_used_at' => now()]);

        // Bind into the request for downstream use
        $request->attributes->set('api_client', $client);
        $request->attributes->set('api_token', $token);
        $request->attributes->set('api_user', $client->user);

        // Make the user available via Auth without session
        Auth::setUser($client->user);

        return $next($request);
    }
}

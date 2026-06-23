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
            return $this->deny('Missing X-Api-Key header.', 'UNAUTHENTICATED', 401);
        }

        $token = ApiClientToken::findByRaw($raw);

        if (! $token) {
            return $this->deny('Invalid API key.', 'UNAUTHENTICATED', 401);
        }

        if (! $token->is_active) {
            return $this->deny('API key is revoked.', 'UNAUTHENTICATED', 401);
        }

        if ($token->isExpired()) {
            return $this->deny('API key has expired.', 'UNAUTHENTICATED', 401);
        }

        $client = $token->apiClient;

        if (! $client || ! $client->is_active) {
            return $this->deny('API client is disabled.', 'UNAUTHENTICATED', 401);
        }

        if ($client->isExpired()) {
            return $this->deny('API client has expired.', 'UNAUTHENTICATED', 401);
        }

        // IP allowlist check (if configured)
        if (! empty($client->allowed_ips)) {
            $ip = $request->ip();
            if (! in_array($ip, $client->allowed_ips, true)) {
                return $this->deny('IP address not allowed.', 'FORBIDDEN', 403);
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

    /** Standardized API error envelope (5A); keeps `error` for backward compatibility. */
    private function deny(string $message, string $code, int $status): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => $message,
            'message' => $message,
            'code'    => $code,
        ], $status);
    }
}

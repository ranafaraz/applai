<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiClientRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startMs = (int) round(microtime(true) * 1000);

        $response = $next($request);

        $client = $request->attributes->get('api_client');
        $user   = $request->attributes->get('api_user');

        if ($client && $user) {
            // Sanitise request summary – never log secrets or full bodies
            $summary = [
                'query'  => $request->query->all(),
                'fields' => array_keys($request->all()),
            ];

            ApiRequestLog::create([
                'api_client_id'   => $client->id,
                'user_id'         => $user->id,
                'endpoint'        => $request->path(),
                'method'          => $request->method(),
                'request_summary' => $summary,
                'response_status' => $response->getStatusCode(),
                'ip_address'      => $request->ip(),
                'user_agent'      => substr((string) $request->userAgent(), 0, 512),
                'duration_ms'     => (int) round(microtime(true) * 1000) - $startMs,
                'created_at'      => now(),
            ]);
        }

        return $response;
    }
}

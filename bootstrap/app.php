<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'super_admin'         => \App\Http\Middleware\RequireSuperAdmin::class,
            'require_admin'       => \App\Http\Middleware\RequireAdmin::class,
            'tenant_active'       => \App\Http\Middleware\EnsureTenantActive::class,
            'api.client'          => \App\Http\Middleware\AuthenticateApiClient::class,
            'api.scope'           => \App\Http\Middleware\CheckApiClientScope::class,
            'api.log'             => \App\Http\Middleware\LogApiClientRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // GPT Actions and other API clients don't send an `Accept: application/json`
        // header, so without this, validation/auth errors on /api/* routes would
        // redirect (302) to the web login/landing page instead of returning JSON —
        // leaving the calling agent with HTML instead of an error it can act on.
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Standardize the JSON error envelope across every API/agent endpoint
        // (5A). All API errors return the same shape so calling agents can branch
        // on a stable `code` and read field-level `errors`:
        //   { success: false, error: "...", message: "...", code: "...", errors: {} }
        // `message` + `errors` are retained for backward compatibility with clients
        // that already read Laravel's default validation envelope.
        $apiJson = static function ($request): bool {
            return $request->is('api/*') || $request->expectsJson();
        };

        $envelope = static function (string $message, string $code, int $status, array $errors = []) {
            $payload = [
                'success' => false,
                'error'   => $message,
                'message' => $message,
                'code'    => $code,
            ];
            if ($errors !== []) {
                $payload['errors'] = $errors;
            }

            return response()->json($payload, $status);
        };

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) use ($apiJson, $envelope) {
            if (! $apiJson($request)) {
                return null;
            }

            return $envelope($e->getMessage(), 'VALIDATION_ERROR', 422, $e->errors());
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) use ($apiJson, $envelope) {
            if (! $apiJson($request)) {
                return null;
            }

            return $envelope('Resource not found.', 'NOT_FOUND', 404);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) use ($apiJson, $envelope) {
            if (! $apiJson($request)) {
                return null;
            }

            return $envelope($e->getMessage() ?: 'Resource not found.', 'NOT_FOUND', 404);
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) use ($apiJson, $envelope) {
            if (! $apiJson($request)) {
                return null;
            }

            return $envelope($e->getMessage() ?: 'Unauthenticated.', 'UNAUTHENTICATED', 401);
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) use ($apiJson, $envelope) {
            if (! $apiJson($request)) {
                return null;
            }

            return $envelope($e->getMessage() ?: 'This action is unauthorized.', 'FORBIDDEN', 403);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) use ($apiJson, $envelope) {
            if (! $apiJson($request)) {
                return null;
            }

            return $envelope($e->getMessage() ?: 'This action is unauthorized.', 'FORBIDDEN', 403);
        });

        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) use ($apiJson, $envelope) {
            if (! $apiJson($request)) {
                return null;
            }

            return $envelope('Too many requests.', 'RATE_LIMITED', 429);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, $request) use ($apiJson, $envelope) {
            if (! $apiJson($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $code = match ($status) {
                400     => 'BAD_REQUEST',
                401     => 'UNAUTHENTICATED',
                403     => 'FORBIDDEN',
                404     => 'NOT_FOUND',
                405     => 'METHOD_NOT_ALLOWED',
                409     => 'CONFLICT',
                422     => 'VALIDATION_ERROR',
                429     => 'RATE_LIMITED',
                default => $status >= 500 ? 'SERVER_ERROR' : 'HTTP_ERROR',
            };

            return $envelope($e->getMessage() ?: 'Request failed.', $code, $status);
        });
    })->create();

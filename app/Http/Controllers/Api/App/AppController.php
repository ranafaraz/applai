<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * Base controller for the mobile API (/api/app/v1).
 *
 * Implements the §3 response & error contract the Flutter client codegens
 * against:
 *   success(single):  { "data": { ... } }
 *   success(list):    { "data": [ ... ], "meta": { page, per_page, total, has_more } }
 *   error:            { "message": "...", "errors": { field: [...] }, "code": "..." }
 *
 * Thrown framework exceptions (validation/auth/404/throttle) are already shaped
 * centrally in bootstrap/app.php, so most error paths need no manual handling —
 * these helpers are for inline controller errors and list shaping.
 */
abstract class AppController extends Controller
{
    /** Single-resource success envelope. */
    protected function data(mixed $resource, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $resource], $status);
    }

    /** Paginated list envelope matching the §3 infinite-scroll contract. */
    protected function paginated(LengthAwarePaginator $paginator, ?callable $transform = null): JsonResponse
    {
        $items = $paginator->getCollection();
        if ($transform) {
            $items = $items->map($transform);
        }

        return response()->json([
            'data' => $items->values(),
            'meta' => [
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total'    => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /** Standardized error envelope (same shape as bootstrap/app.php). */
    protected function error(string $message, string $code = 'BAD_REQUEST', int $status = 422, array $errors = []): JsonResponse
    {
        $payload = ['message' => $message, 'code' => $code];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->error($message, 'NOT_FOUND', 404);
    }
}

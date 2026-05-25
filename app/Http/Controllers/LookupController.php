<?php

namespace App\Http\Controllers;

use App\Models\Lookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * Return all lookup values of a given type for the current user's tenant.
     * Used by autocomplete inputs on contact / opportunity forms.
     *
     *   GET /lookups/{type}        -> [{value, meta, is_system}, ...]
     */
    public function index(Request $request, string $type): JsonResponse
    {
        $allowed = ['country', 'industry', 'source', 'city', 'designation'];
        if (! in_array($type, $allowed, true)) {
            return response()->json(['error' => 'unknown type'], 404);
        }

        $tenantId = $request->user()->tenant_id;
        $items = Lookup::listFor($type, $tenantId);

        return response()->json(
            $items->map(fn ($l) => ['value' => $l->value, 'meta' => $l->meta, 'is_system' => $l->is_system])->all()
        );
    }
}

<?php

namespace App\Http\Controllers\Api\App;

use App\Models\Tag;
use App\Support\OpportunityStage;
use App\Support\OpportunityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Meta endpoints (§4.10) so the app never hardcodes stage/type vocabularies.
 */
class MetaController extends AppController
{
    public function stages(): JsonResponse
    {
        return response()->json(['data' => OpportunityStage::meta()]);
    }

    public function types(): JsonResponse
    {
        return response()->json(['data' => OpportunityType::meta()]);
    }

    public function tags(Request $request): JsonResponse
    {
        $tags = Tag::forCurrentUser()
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])
            ->values();

        return response()->json(['data' => $tags]);
    }
}

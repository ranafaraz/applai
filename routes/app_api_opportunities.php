<?php

use App\Http\Controllers\Api\App\MetaController;
use App\Http\Controllers\Api\App\OpportunityController;
use Illuminate\Support\Facades\Route;

/*
| Opportunities (§4.2) + Meta (§4.10) — mounted inside the authenticated
| /api/app/v1 group (see routes/app_api.php). Already behind auth:sanctum.
*/

// Meta — stage/type/tag vocabularies so the app never hardcodes them.
Route::get('meta/stages', [MetaController::class, 'stages']);
Route::get('meta/types', [MetaController::class, 'types']);
Route::get('meta/tags', [MetaController::class, 'tags']);

// Opportunities
Route::get('opportunities', [OpportunityController::class, 'index']);
Route::post('opportunities', [OpportunityController::class, 'store']);
Route::get('opportunities/{id}', [OpportunityController::class, 'show'])->whereNumber('id');
Route::patch('opportunities/{id}', [OpportunityController::class, 'update'])->whereNumber('id');
Route::delete('opportunities/{id}', [OpportunityController::class, 'destroy'])->whereNumber('id');

Route::patch('opportunities/{id}/stage', [OpportunityController::class, 'changeStage'])->whereNumber('id');
Route::get('opportunities/{id}/emails', [OpportunityController::class, 'emails'])->whereNumber('id');
Route::get('opportunities/{id}/timeline', [OpportunityController::class, 'timeline'])->whereNumber('id');
Route::post('opportunities/{id}/contact', [OpportunityController::class, 'linkContact'])->whereNumber('id');
Route::delete('opportunities/{id}/contact/{contactId}', [OpportunityController::class, 'unlinkContact'])
    ->whereNumber('id')->whereNumber('contactId');

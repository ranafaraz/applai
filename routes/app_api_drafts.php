<?php

use App\Http\Controllers\Api\App\DraftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — AI Drafts  (/api/app/v1/drafts)  §4.4
|--------------------------------------------------------------------------
| NO /send endpoint — send-payload returns a mailto: URL only.
*/

Route::prefix('drafts')->group(function () {
    Route::get('/',                [DraftController::class, 'index']);
    Route::post('/generate',       [DraftController::class, 'generate']);

    Route::get('/{id}',            [DraftController::class, 'show']);
    Route::patch('/{id}',          [DraftController::class, 'update']);
    Route::delete('/{id}',         [DraftController::class, 'destroy']);

    Route::post('/{id}/regenerate', [DraftController::class, 'regenerate']);
    Route::post('/{id}/mark-ready', [DraftController::class, 'markReady']);
    Route::post('/{id}/reject',     [DraftController::class, 'reject']);
    Route::get('/{id}/send-payload',[DraftController::class, 'sendPayload']);
});

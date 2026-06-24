<?php

use App\Http\Controllers\Api\App\ContactController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — Contacts  (/api/app/v1/contacts)  §4.3
|--------------------------------------------------------------------------
*/

Route::prefix('contacts')->group(function () {
    Route::get('/',    [ContactController::class, 'index']);
    Route::post('/',   [ContactController::class, 'store']);

    Route::get('/{id}',    [ContactController::class, 'show']);
    Route::patch('/{id}',  [ContactController::class, 'update']);
    Route::delete('/{id}', [ContactController::class, 'destroy']);

    Route::post('/{id}/suppress',   [ContactController::class, 'suppress']);
    Route::delete('/{id}/suppress', [ContactController::class, 'unsuppress']);

    Route::get('/{id}/opportunities', [ContactController::class, 'opportunities']);
    Route::get('/{id}/emails',        [ContactController::class, 'emails']);
});

<?php

use App\Http\Controllers\Api\App\AnalyticsController;
use Illuminate\Support\Facades\Route;

// Analytics (§4.6)
Route::prefix('analytics')->group(function () {
    Route::get('overview', [AnalyticsController::class, 'overview']);
    Route::get('pipeline', [AnalyticsController::class, 'pipeline']);
    Route::get('activity', [AnalyticsController::class, 'activity']);
});

<?php

use App\Http\Controllers\Api\Gpt\V1\HealthController;
use App\Http\Controllers\Api\Gpt\V1\MeController;
use App\Http\Controllers\Api\Gpt\V1\DashboardSummaryController;
use App\Http\Controllers\Api\Gpt\V1\OpportunityController;
use App\Http\Controllers\Api\Gpt\V1\ContactController;
use App\Http\Controllers\Api\Gpt\V1\EmailDraftController;
use App\Http\Controllers\Api\Gpt\V1\FollowUpController;
use App\Http\Controllers\Api\Gpt\V1\ReplyController;
use App\Http\Controllers\Api\Gpt\V1\IngestionController;
use App\Http\Controllers\Api\Gpt\V1\ConfirmationController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// GPT / MCP / n8n API  –  /api/gpt/v1
// ---------------------------------------------------------------------------

// Health is public — schema marks it security:[] so ChatGPT won't send auth header here.
Route::get('gpt/v1/health', HealthController::class)->middleware('throttle:60,1');

Route::prefix('gpt/v1')
    ->middleware(['api.client', 'api.log', 'throttle:60,1'])
    ->group(function () {

        // Identity
        Route::get('me', MeController::class);

        // Dashboard
        Route::get('dashboard-summary', DashboardSummaryController::class)
            ->middleware('api.scope:dashboard:read');

        // Opportunities
        Route::get('opportunities', [OpportunityController::class, 'index'])
            ->middleware('api.scope:opportunities:read');
        Route::post('opportunities', [OpportunityController::class, 'store'])
            ->middleware(['api.scope:opportunities:write', 'throttle:20,1']);
        Route::get('opportunities/{id}', [OpportunityController::class, 'show'])
            ->middleware('api.scope:opportunities:read');
        Route::post('opportunities/{id}/notes', [OpportunityController::class, 'addNote'])
            ->middleware(['api.scope:notes:write', 'throttle:20,1']);

        // Contacts
        Route::get('contacts', [ContactController::class, 'index'])
            ->middleware('api.scope:contacts:read');
        Route::post('contacts', [ContactController::class, 'store'])
            ->middleware(['api.scope:contacts:write', 'throttle:20,1']);
        Route::get('contacts/{id}', [ContactController::class, 'show'])
            ->middleware('api.scope:contacts:read');
        Route::post('contacts/{id}/notes', [ContactController::class, 'addNote'])
            ->middleware(['api.scope:notes:write', 'throttle:20,1']);

        // Email Drafts (never sends – requires_review is always true)
        Route::get('email-drafts', [EmailDraftController::class, 'index'])
            ->middleware('api.scope:drafts:read');
        Route::post('email-drafts', [EmailDraftController::class, 'store'])
            ->middleware(['api.scope:drafts:create', 'throttle:5,1']);

        // Follow-ups (reminder-only in MVP)
        Route::get('follow-ups/due', [FollowUpController::class, 'due'])
            ->middleware('api.scope:followups:read');
        Route::post('follow-ups', [FollowUpController::class, 'store'])
            ->middleware(['api.scope:followups:create', 'throttle:20,1']);

        // Replies
        Route::get('replies/recent', [ReplyController::class, 'recent'])
            ->middleware('api.scope:replies:read');

        // Bulk ingestion endpoints (n8n / scrapers)
        Route::post('ingestion/opportunities', [IngestionController::class, 'opportunities'])
            ->middleware(['api.scope:opportunities:write', 'throttle:10,1']);
        Route::post('ingestion/contacts', [IngestionController::class, 'contacts'])
            ->middleware(['api.scope:contacts:write', 'throttle:10,1']);

        // Confirmation requests (multi-step AI action gating)
        Route::post('confirmations', [ConfirmationController::class, 'store'])
            ->middleware('throttle:10,1');
        Route::get('confirmations/{id}', [ConfirmationController::class, 'show']);
    });

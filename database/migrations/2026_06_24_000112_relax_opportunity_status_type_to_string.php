<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile app (§4.2/§8) uses a richer stage vocabulary than the original
 * enum: draft, applied, replied, interview, offer, won, closed, archived — and
 * opportunity types job, phd, scholarship, grant, freelance. The legacy enum
 * columns can't hold the new values, so widen `status` and `type` to plain
 * strings. App-layer validation (App\Support\OpportunityStage / OpportunityType)
 * is now the source of truth; legacy stored values are mapped on read.
 *
 * No data migration needed — existing values remain valid strings and are
 * normalized to canonical stages when returned to the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
            $table->string('type')->default('job')->change();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->enum('status', [
                'draft', 'active', 'waiting_reply', 'replied',
                'interview', 'offer', 'rejected', 'withdrawn', 'closed',
            ])->default('draft')->change();
            $table->enum('type', ['job', 'scholarship', 'research', 'grant', 'networking'])
                ->default('job')->change();
        });
    }
};

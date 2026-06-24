<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile-app (/api/app/v1) support columns on users:
 *  - tracking_types: json array of what the user tracks (job|phd|scholarship|grant|freelance),
 *    captured on the onboarding "what are you tracking" screen (1.5).
 *  - avatar_url: profile photo URL set from Settings (7.1).
 *  - soft deletes: account deletion "Danger Zone" soft-deletes + revokes tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('tracking_types')->nullable()->after('avatar');
            $table->string('avatar_url')->nullable()->after('tracking_types');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tracking_types', 'avatar_url']);
            $table->dropSoftDeletes();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh tokens for the mobile API (/api/app/v1/auth/refresh).
 *
 * Sanctum personal-access tokens serve as short-lived Bearer access tokens.
 * These opaque refresh tokens are long-lived and let the app silently obtain
 * a new access token without re-prompting for credentials. Stored hashed at
 * rest (sha256), mirroring ApiClientToken — the raw value is shown to the
 * client exactly once at issuance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_refresh_tokens');
    }
};

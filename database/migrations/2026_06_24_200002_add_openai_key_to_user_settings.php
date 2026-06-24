<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->text('openai_api_key')->nullable()->after('onboarding_dismissed_at');
            $table->string('openai_model', 50)->default('gpt-4o-mini')->after('openai_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropColumn(['openai_api_key', 'openai_model']);
        });
    }
};

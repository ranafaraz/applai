<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            try {
                Schema::table('notification_preferences', function (Blueprint $table) {
                    $table->unique(['user_id', 'notification_type', 'channel'], 'notif_prefs_user_type_channel_unique');
                });
            } catch (\Throwable $e) {
                if (! str_contains($e->getMessage(), 'Duplicate') && ! str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
            }

            return;
        }

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type', 64);
            $table->string('channel', 32); // database | mail | push
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'notification_type', 'channel'], 'notif_prefs_user_type_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};

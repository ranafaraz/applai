<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            // Only add on MySQL/MariaDB; skip for SQLite (test suite).
            if (DB::getDriverName() !== 'sqlite') {
                $table->boolean('cancel_if_replied')->default(true)->after('is_follow_up');
            }
        });

        // SQLite-safe fallback for the test suite.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE email_messages ADD COLUMN cancel_if_replied TINYINT(1) NOT NULL DEFAULT 1');
        }
    }

    public function down(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn('cancel_if_replied');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The mobile drafts API (§4.4) introduces two new email_messages.status values:
 * `approved` (mark-ready) and `rejected`. The column is a MySQL enum in prod;
 * widen it. On SQLite (the test DB) an enum is a CHECK-constrained varchar that
 * would reject the new values, so there we drop the constraint by changing the
 * column to a plain string — matching how opportunities.status was relaxed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE email_messages MODIFY COLUMN status ENUM('draft','scheduled','queued','sending','sent','failed','cancelled','approved','rejected') NOT NULL DEFAULT 'draft'");

            return;
        }

        Schema::table('email_messages', function (Blueprint $table) {
            $table->string('status')->default('draft')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE email_messages MODIFY COLUMN status ENUM('draft','scheduled','queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft'");
        }
    }
};

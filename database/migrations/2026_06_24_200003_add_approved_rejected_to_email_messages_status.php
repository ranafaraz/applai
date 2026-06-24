<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE email_messages MODIFY COLUMN status ENUM('draft','scheduled','queued','sending','sent','failed','cancelled','approved','rejected') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE email_messages MODIFY COLUMN status ENUM('draft','scheduled','queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft'");
    }
};

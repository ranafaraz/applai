<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add 'mcp' to social_posts.created_source. LinkedInPostController::store()
     * writes created_source='mcp' for MCP-source clients, but the column's enum
     * was created without it — causing a 1265 "Data truncated" error (HTTP 500)
     * on every MCP-originated LinkedIn post create.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE social_posts MODIFY COLUMN created_source "
            . "ENUM('manual','chatgpt','template','import','mcp') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        // Collapse any 'mcp' rows so the narrower enum reverts cleanly.
        DB::statement("UPDATE social_posts SET created_source='chatgpt' WHERE created_source='mcp'");
        DB::statement("ALTER TABLE social_posts MODIFY COLUMN created_source "
            . "ENUM('manual','chatgpt','template','import') NOT NULL DEFAULT 'manual'");
    }
};

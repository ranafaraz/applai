<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_client_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('source', ['custom_gpt', 'mcp', 'n8n', 'internal_agent', 'other']);
            $table->string('action', 100);
            $table->string('entity_type', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low');
            $table->text('input_summary')->nullable();
            $table->text('output_summary')->nullable();
            $table->enum('status', ['success', 'failure', 'blocked'])->default('success');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_audit_logs');
    }
};

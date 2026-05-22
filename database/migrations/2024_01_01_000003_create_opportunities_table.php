<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->enum('type', ['job', 'scholarship', 'research', 'grant', 'networking'])->default('job');
            $table->string('organization')->nullable();
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->enum('status', [
                'draft',
                'active',
                'waiting_reply',
                'replied',
                'interview',
                'offer',
                'rejected',
                'withdrawn',
                'closed',
            ])->default('draft');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->date('deadline')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};

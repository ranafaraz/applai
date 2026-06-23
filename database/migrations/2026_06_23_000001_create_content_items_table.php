<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // Editorial type: blog, linkedin, youtube, newsletter, tweet, etc.
            $table->string('content_type')->nullable();
            // Distribution channel/platform (free-form).
            $table->string('channel')->nullable();
            // idea → draft → scheduled → published → archived
            $table->string('status')->default('idea');
            $table->longText('body')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('published_url')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'scheduled_for']);
            $table->index(['user_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_items');
    }
};

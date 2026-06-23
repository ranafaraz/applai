<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // YouTube video id (e.g. dQw4w9WgXcQ) — set once the video exists on YouTube.
            $table->string('video_id')->nullable();
            $table->string('url')->nullable();
            $table->longText('description')->nullable();
            // idea → scripting → recording → editing → scheduled → published → archived
            $table->string('status')->default('idea');
            // public | unlisted | private
            $table->string('visibility')->default('public');
            $table->string('channel')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('tags')->nullable();
            // Cached engagement stats (synced from YouTube; not authoritative).
            $table->unsignedBigInteger('view_count')->default(0);
            $table->unsignedBigInteger('like_count')->default(0);
            $table->unsignedBigInteger('comment_count')->default(0);
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'scheduled_for']);
            $table->index(['user_id', 'video_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_videos');
    }
};

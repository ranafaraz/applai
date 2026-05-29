<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_attachment_follow_up', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follow_up_id')->constrained('follow_ups')->onDelete('cascade');
            $table->foreignId('api_attachment_id')->constrained('api_attachments')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['follow_up_id', 'api_attachment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_attachment_follow_up');
    }
};

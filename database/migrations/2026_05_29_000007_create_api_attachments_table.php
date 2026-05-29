<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('added_by_api_client_id')->nullable()->constrained('api_clients')->onDelete('set null');
            $table->string('filename', 500);
            $table->string('public_url', 2048);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->string('category', 50)->default('other');
            $table->text('notes')->nullable();
            $table->string('validation_status', 20)->default('valid'); // valid, warning, rejected
            $table->json('validation_warnings')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_attachments');
    }
};

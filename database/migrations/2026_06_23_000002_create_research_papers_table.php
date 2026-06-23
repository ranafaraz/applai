<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // Free-form list of author names.
            $table->json('authors')->nullable();
            $table->longText('abstract')->nullable();
            // External identifiers / links.
            $table->string('url')->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('arxiv_id')->nullable();
            $table->string('doi')->nullable();
            $table->string('venue')->nullable();
            $table->date('published_date')->nullable();
            // to_read → reading → read → archived
            $table->string('status')->default('to_read');
            $table->longText('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'arxiv_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_papers');
    }
};

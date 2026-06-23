<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Optional links to CRM entities (validated for ownership at write time).
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->string('title');
            // draft → sent → accepted | rejected | expired
            $table->string('status')->default('draft');
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->longText('body')->nullable();
            $table->string('url')->nullable();
            $table->date('valid_until')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'opportunity_id']);
            $table->index(['user_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freelance_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Optional links to CRM entities (validated for ownership at write time).
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->string('title');
            // Free-form client label when no contact is linked.
            $table->string('client_name')->nullable();
            // Marketplace/source: upwork, fiverr, direct, referral, etc.
            $table->string('platform')->nullable();
            // lead → proposal → active → on_hold → completed | cancelled
            $table->string('status')->default('lead');
            // hourly | fixed
            $table->string('rate_type')->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('hours_logged', 8, 2)->default(0);
            $table->longText('description')->nullable();
            $table->string('url')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
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
        Schema::dropIfExists('freelance_projects');
    }
};

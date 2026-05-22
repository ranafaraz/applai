<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('website')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'suppressed', 'bounced'])->default('active');
            $table->string('source')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

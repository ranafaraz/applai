<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->enum('type', [
                'initial_outreach',
                'follow_up',
                'thank_you',
                'networking',
                'other',
            ])->default('initial_outreach');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('opportunity_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('email_template_id')
                ->nullable()
                ->constrained('email_templates')
                ->onDelete('set null');
            $table->foreignId('email_message_id')
                ->nullable()
                ->constrained('email_messages')
                ->onDelete('set null');

            $table->unsignedSmallInteger('follow_up_number')->default(1);
            $table->timestamp('due_at');
            $table->timestamp('sent_at')->nullable();

            $table->enum('status', ['pending', 'sent', 'cancelled', 'skipped'])->default('pending');
            $table->string('cancel_reason')->nullable();

            $table->string('subject')->nullable();
            $table->text('body')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};

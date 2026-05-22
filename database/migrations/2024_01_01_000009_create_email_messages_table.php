<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('opportunity_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('template_id')
                ->nullable()
                ->constrained('email_templates')
                ->onDelete('set null');

            $table->string('message_id')->nullable()->index();
            $table->string('subject');
            $table->text('body');
            $table->string('to_email');
            $table->string('to_name')->nullable();
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();

            $table->enum('status', [
                'draft',
                'scheduled',
                'queued',
                'sending',
                'sent',
                'failed',
                'cancelled',
            ])->default('draft');
            $table->enum('direction', ['outbound', 'inbound'])->default('outbound');

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->boolean('is_follow_up')->default(false);
            $table->unsignedSmallInteger('follow_up_number')->default(0);
            $table->foreignId('parent_message_id')
                ->nullable()
                ->constrained('email_messages')
                ->onDelete('set null');

            $table->timestamp('opened_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};

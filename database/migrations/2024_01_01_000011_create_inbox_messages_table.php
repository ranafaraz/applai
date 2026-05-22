<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('email_account_id')->constrained()->onDelete('cascade');

            $table->string('uid')->index();
            $table->string('message_id')->nullable()->index();
            $table->string('in_reply_to')->nullable();

            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->text('body_text')->nullable();
            $table->text('body_html')->nullable();

            $table->timestamp('received_at');
            $table->boolean('is_read')->default(false);

            $table->foreignId('matched_contact_id')
                ->nullable()
                ->constrained('contacts')
                ->onDelete('set null');
            $table->foreignId('matched_opportunity_id')
                ->nullable()
                ->constrained('opportunities')
                ->onDelete('set null');
            $table->foreignId('matched_outbound_id')
                ->nullable()
                ->constrained('email_messages')
                ->onDelete('set null');

            $table->enum('review_status', ['pending', 'reviewed', 'ignored'])->default('pending');
            $table->enum('sentiment', ['positive', 'neutral', 'negative', 'unknown'])->default('unknown');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_messages');
    }
};

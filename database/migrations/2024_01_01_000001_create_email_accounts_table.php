<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email');
            $table->string('from_name');

            // SMTP settings
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port');
            $table->enum('smtp_encryption', ['tls', 'ssl', 'none'])->default('tls');
            $table->string('smtp_username');
            $table->text('smtp_password'); // stored encrypted

            // IMAP settings
            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port');
            $table->string('imap_encryption')->default('ssl');
            $table->string('imap_username');
            $table->text('imap_password'); // stored encrypted

            // Rate limiting
            $table->unsignedInteger('daily_limit')->default(50);
            $table->unsignedInteger('hourly_limit')->default(10);
            $table->unsignedInteger('min_delay_seconds')->default(30);
            $table->unsignedInteger('emails_sent_today')->default(0);
            $table->timestamp('last_reset_at')->nullable();

            // Sync status
            $table->timestamp('last_sync_at')->nullable();
            $table->enum('sync_status', ['idle', 'syncing', 'error'])->default('idle');

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};

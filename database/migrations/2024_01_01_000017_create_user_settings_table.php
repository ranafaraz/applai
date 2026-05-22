<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('timezone')->default('UTC');
            $table->string('date_format')->default('Y-m-d');
            $table->unsignedSmallInteger('default_follow_up_days')->default(5);
            $table->foreignId('default_email_account_id')
                ->nullable()
                ->constrained('email_accounts')
                ->onDelete('set null');
            $table->boolean('notify_on_reply')->default(true);
            $table->boolean('notify_on_bounce')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};

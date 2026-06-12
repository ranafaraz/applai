<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_messages', function (Blueprint $table) {
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->unsignedInteger('open_count')->default(0)->after('clicked_at');
            $table->unsignedInteger('click_count')->default(0)->after('open_count');
        });

        Schema::create('email_link_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_message_id')->constrained('email_messages')->onDelete('cascade');
            $table->string('url', 2048);
            $table->string('user_agent', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('clicked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_link_clicks');

        Schema::table('email_messages', function (Blueprint $table) {
            $table->dropColumn(['clicked_at', 'open_count', 'click_count']);
        });
    }
};

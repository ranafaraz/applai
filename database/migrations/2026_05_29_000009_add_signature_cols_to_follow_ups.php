<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->foreignId('email_signature_id')->nullable()->after('body')
                ->constrained('email_signatures')->onDelete('set null');
            $table->mediumText('rendered_signature')->nullable()->after('email_signature_id');
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropForeign(['email_signature_id']);
            $table->dropColumn(['email_signature_id', 'rendered_signature']);
        });
    }
};

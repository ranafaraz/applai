<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->foreignId('opportunity_id')->nullable()->change();
            $table->foreignId('email_account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
            $table->foreignId('opportunity_id')->nullable(false)->change();
            $table->foreignId('email_account_id')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $t) {
            if (! Schema::hasColumn('email_accounts', 'smtp_status')) {
                $t->string('smtp_status', 32)->nullable()->after('imap_password');
            }
            if (! Schema::hasColumn('email_accounts', 'smtp_last_checked_at')) {
                $t->timestamp('smtp_last_checked_at')->nullable()->after('smtp_status');
            }
            if (! Schema::hasColumn('email_accounts', 'smtp_last_error')) {
                $t->text('smtp_last_error')->nullable()->after('smtp_last_checked_at');
            }
            if (! Schema::hasColumn('email_accounts', 'imap_status')) {
                $t->string('imap_status', 32)->nullable()->after('smtp_last_error');
            }
            if (! Schema::hasColumn('email_accounts', 'imap_last_checked_at')) {
                $t->timestamp('imap_last_checked_at')->nullable()->after('imap_status');
            }
            if (! Schema::hasColumn('email_accounts', 'imap_last_error')) {
                $t->text('imap_last_error')->nullable()->after('imap_last_checked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $t) {
            foreach (['smtp_status', 'smtp_last_checked_at', 'smtp_last_error', 'imap_status', 'imap_last_checked_at', 'imap_last_error'] as $col) {
                if (Schema::hasColumn('email_accounts', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};

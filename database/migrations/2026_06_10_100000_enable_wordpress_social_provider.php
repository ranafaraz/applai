<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('social_providers')->updateOrInsert(
            ['key' => 'wordpress'],
            [
                'name' => 'WordPress',
                'status' => 'enabled',
                'capabilities_json' => json_encode(['html', 'image', 'featured_image', 'draft', 'publish']),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            try {
                DB::statement('ALTER TABLE social_accounts DROP INDEX social_accounts_user_id_provider_id_unique');
            } catch (Throwable) {
                // Fresh installs use a non-unique index now; existing installs may already be migrated.
            }
        }

        Schema::table('social_post_targets', function (Blueprint $table) {
            if (! Schema::hasColumn('social_post_targets', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('scheduled_at');
            }
        });
    }

    public function down(): void
    {
        DB::table('social_providers')->where('key', 'wordpress')->update([
            'status' => 'coming_soon',
            'capabilities_json' => null,
            'updated_at' => now(),
        ]);

        Schema::table('social_post_targets', function (Blueprint $table) {
            if (Schema::hasColumn('social_post_targets', 'locked_at')) {
                $table->dropColumn('locked_at');
            }
        });
    }
};

<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeDeletedTenantsCommand extends Command
{
    protected $signature   = 'tenants:purge-deleted {--grace-days=30}';
    protected $description = 'Hard-delete tenants whose deletion grace period has elapsed';

    public function handle(TenantDataService $service): int
    {
        $cutoff = now()->subDays((int) $this->option('grace-days'));

        $due = Tenant::where('status', 'cancelled')
            ->whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', $cutoff)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No tenants due for purge.');
            return 0;
        }

        foreach ($due as $tenant) {
            $this->info("Purging tenant #{$tenant->id} ({$tenant->name})…");

            try {
                $service->purge($tenant);
            } catch (\Throwable $e) {
                Log::error('tenants:purge-deleted failed', [
                    'tenant_id' => $tenant->id,
                    'error'     => $e->getMessage(),
                ]);
                $this->error("  Failed: {$e->getMessage()}");
            }
        }

        return 0;
    }
}

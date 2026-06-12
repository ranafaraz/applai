<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\DataExportReadyNotification;
use App\Services\TenantDataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportTenantDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public readonly int $userId)
    {
    }

    public function handle(TenantDataService $service): void
    {
        $user = User::find($this->userId);

        if (! $user || ! $user->tenant) {
            return;
        }

        $path = $service->export($user->tenant);

        $user->notify(new DataExportReadyNotification($path));
    }
}

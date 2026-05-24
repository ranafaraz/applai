<?php

namespace App\Jobs;

use App\Models\OpportunityImport;
use App\Services\OpportunityImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessOpportunityImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;

    public function __construct(
        public readonly OpportunityImport $import,
    ) {}

    public function handle(OpportunityImportService $service): void
    {
        Log::info('ProcessOpportunityImportJob: starting', [
            'opportunity_import_id' => $this->import->id,
            'file_name'             => $this->import->file_name,
        ]);

        try {
            $service->parseAndStore($this->import);
            $service->processImport($this->import);

            Log::info('ProcessOpportunityImportJob: completed', [
                'opportunity_import_id' => $this->import->id,
                'imported_rows'         => $this->import->fresh()?->imported_rows,
            ]);
        } catch (Throwable $e) {
            Log::error('ProcessOpportunityImportJob: failed', [
                'opportunity_import_id' => $this->import->id,
                'error'                 => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessOpportunityImportJob: permanently failed', [
            'opportunity_import_id' => $this->import->id,
            'error'                 => $exception?->getMessage(),
        ]);

        $this->import->update([
            'status'        => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Unknown error.',
        ]);
    }
}

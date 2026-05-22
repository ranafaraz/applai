<?php

namespace App\Jobs;

use App\Models\ContactImport;
use App\Services\CsvImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessContactImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Large CSV files may take time – allow up to 10 minutes.
     */
    public int $timeout = 600;

    /**
     * Only attempt once to avoid duplicate contact creation.
     */
    public int $tries = 1;

    public function __construct(
        public readonly ContactImport $import,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CsvImportService $csvImportService): void
    {
        Log::info('ProcessContactImportJob: starting', [
            'contact_import_id' => $this->import->id,
            'file_name'         => $this->import->file_name,
        ]);

        try {
            $csvImportService->processImport($this->import);

            Log::info('ProcessContactImportJob: completed', [
                'contact_import_id' => $this->import->id,
                'imported_rows'     => $this->import->fresh()?->imported_rows,
            ]);
        } catch (Throwable $e) {
            Log::error('ProcessContactImportJob: failed', [
                'contact_import_id' => $this->import->id,
                'error'             => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('ProcessContactImportJob: permanently failed', [
            'contact_import_id' => $this->import->id,
            'error'             => $exception?->getMessage(),
        ]);

        $this->import->update([
            'status'        => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Unknown error.',
        ]);
    }
}

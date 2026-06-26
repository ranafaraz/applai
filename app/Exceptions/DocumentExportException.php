<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by DocumentExportService when a requested export cannot be produced
 * (unsupported format, non-content document, or no tabular data for CSV).
 * Carries the HTTP status the API/web layer should surface.
 */
class DocumentExportException extends RuntimeException
{
    public int $status;

    public function __construct(string $message, int $status = 422)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

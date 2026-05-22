<?php

namespace App\Events;

use App\Models\EmailMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly EmailMessage $message,
    ) {}
}

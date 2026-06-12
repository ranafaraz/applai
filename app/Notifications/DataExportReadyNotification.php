<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class DataExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE = 'data_export_ready';

    public function __construct(public readonly string $exportPath)
    {
    }

    /** Always delivered in-app — exports are explicitly requested. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $url = URL::temporarySignedRoute('data-export.download', now()->addDays(7), [
            'file' => basename($this->exportPath),
        ]);

        return [
            'type'         => self::TYPE,
            'title'        => 'Your data export is ready',
            'body'         => 'Download contains all of your workspace data as JSON and CSV. The link is valid for 7 days.',
            'icon'         => 'download',
            'action_url'   => $url,
            'action_label' => 'Download export',
            'export_file'  => basename($this->exportPath),
        ];
    }
}

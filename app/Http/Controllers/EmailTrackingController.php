<?php

namespace App\Http\Controllers;

use App\Models\EmailMessage;
use App\Services\EmailTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public (signed-URL) endpoints hit by recipients' mail clients. Routes are
 * protected by Laravel URL signatures, not sessions.
 */
class EmailTrackingController extends Controller
{
    /** 1x1 transparent GIF, base64-decoded once. */
    private const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __construct(private EmailTrackingService $tracking)
    {
    }

    public function open(Request $request, int $message): Response
    {
        $emailMessage = EmailMessage::find($message);

        if ($emailMessage) {
            $this->tracking->recordOpen($emailMessage, $request);
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }

    public function click(Request $request, int $message): RedirectResponse
    {
        $url = (string) $request->query('url');

        // The signature already guarantees the url originated from a message
        // we sent; re-validate the scheme as defense in depth.
        if (! preg_match('#^https?://#i', $url)) {
            abort(404);
        }

        $emailMessage = EmailMessage::find($message);

        if ($emailMessage) {
            $this->tracking->recordClick($emailMessage, $url, $request);
        }

        return redirect()->away($url);
    }
}

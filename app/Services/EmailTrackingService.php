<?php

namespace App\Services;

use App\Models\EmailLinkClick;
use App\Models\EmailMessage;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Open-pixel and click tracking for outbound email. Tracking is a paid
 * feature (config/plans.php email_tracking); the stored message body is
 * never modified — tracking markup is injected into the outgoing MIME only.
 */
class EmailTrackingService
{
    /** Scanner/prefetch user agents whose hits shouldn't count as opens. */
    private const BOT_AGENT_PATTERNS = [
        'GoogleImageProxy', 'Google-Safety', 'Barracuda', 'Mimecast',
        'ProofPoint', 'Symantec', 'bingbot', 'YahooMailProxy', 'curl', 'wget',
        'python-requests', 'Go-http-client',
    ];

    public function __construct(private PlanLimitsService $planLimits)
    {
    }

    public function trackingEnabled(EmailMessage $message): bool
    {
        $tenant = $message->tenant_id ? Tenant::find($message->tenant_id) : null;

        return $tenant !== null && $this->planLimits->hasFeature($tenant, 'email_tracking');
    }

    /**
     * Return the HTML to actually send: original body with rewritten links
     * and an appended open pixel.
     */
    public function prepareHtml(EmailMessage $message, string $html): string
    {
        if (! $this->trackingEnabled($message)) {
            return $html;
        }

        $html = $this->rewriteLinks($message, $html);

        $pixelUrl = URL::signedRoute('track.open', ['message' => $message->id]);

        return $html . '<img src="' . e($pixelUrl) . '" width="1" height="1" alt="" style="display:none;border:0;" />';
    }

    /** Record an open-pixel hit (idempotent on opened_at). */
    public function recordOpen(EmailMessage $message, Request $request): void
    {
        if ($this->isBotHit($message, $request)) {
            return;
        }

        $message->increment('open_count');

        if ($message->opened_at === null) {
            $message->update(['opened_at' => now()]);
        }
    }

    /** Record a click and return the destination URL. */
    public function recordClick(EmailMessage $message, string $url, Request $request): void
    {
        EmailLinkClick::create([
            'email_message_id' => $message->id,
            'url'              => $url,
            'user_agent'       => substr((string) $request->userAgent(), 0, 500),
            'ip_address'       => $request->ip(),
            'clicked_at'       => now(),
        ]);

        if ($this->isBotHit($message, $request)) {
            return;
        }

        $message->increment('click_count');

        $updates = [];
        if ($message->clicked_at === null) {
            $updates['clicked_at'] = now();
        }
        // A click implies the email was opened even if the pixel was blocked.
        if ($message->opened_at === null) {
            $updates['opened_at'] = now();
        }
        if ($updates) {
            $message->update($updates);
        }
    }

    private function rewriteLinks(EmailMessage $message, string $html): string
    {
        return preg_replace_callback(
            '/(<a\b[^>]*\bhref=")(https?:\/\/[^"]+)(")/i',
            function (array $m) use ($message) {
                $tracked = URL::signedRoute('track.click', [
                    'message' => $message->id,
                    'url'     => $m[2],
                ]);

                return $m[1] . e($tracked) . $m[3];
            },
            $html,
        ) ?? $html;
    }

    /**
     * Security scanners fetch links/pixels within seconds of delivery and
     * with recognizable user agents — those hits shouldn't count as
     * engagement.
     */
    private function isBotHit(EmailMessage $message, Request $request): bool
    {
        if ($message->sent_at && $message->sent_at->diffInSeconds(now()) < 5) {
            return true;
        }

        $agent = (string) $request->userAgent();

        foreach (self::BOT_AGENT_PATTERNS as $pattern) {
            if (stripos($agent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}

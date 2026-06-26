<?php

namespace App\Http\Controllers\Api\Gpt\V1;

use App\Models\EmailAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailHealthController extends GptController
{
    /**
     * Check SPF, DKIM, and DMARC DNS records for the user's active sending domain.
     * Scope: drafts:read.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $this->apiUser($request);

        $account = EmailAccount::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        if (! $account) {
            return response()->json(['error' => 'No active email account configured.'], 422);
        }

        $domain = strtolower(substr(strrchr($account->email, '@'), 1));

        if (! $domain || $domain === $account->email) {
            return response()->json(['error' => 'Could not determine sending domain from email account.'], 422);
        }

        $spf   = $this->checkSpf($domain);
        $dkim  = $this->checkDkim($domain);
        $dmarc = $this->checkDmarc($domain);

        $overall = ($spf['found'] && $dmarc['found']) ? 'pass' : 'warn';

        return response()->json([
            'data' => [
                'domain'  => $domain,
                'overall' => $overall,
                'spf'     => $spf,
                'dkim'    => $dkim,
                'dmarc'   => $dmarc,
                'notice'  => $overall === 'warn'
                    ? 'One or more DNS records are missing. Emails may land in spam or be rejected.'
                    : 'SPF and DMARC records detected. Deliverability looks healthy.',
            ],
        ]);
    }

    private function checkSpf(string $domain): array
    {
        $records = @dns_get_record($domain, DNS_TXT) ?: [];
        foreach ($records as $r) {
            $txt = $r['txt'] ?? $r['entries'][0] ?? '';
            if (str_starts_with($txt, 'v=spf1')) {
                return ['found' => true, 'record' => $txt];
            }
        }
        return ['found' => false, 'record' => null, 'hint' => "Add a TXT record to {$domain} starting with 'v=spf1'."];
    }

    private function checkDkim(string $domain): array
    {
        // Try the most common DKIM selectors used by popular mail providers.
        $selectors = ['default', 'mail', 'google', 'k1', 'dkim', 'selector1', 'selector2', 's1', 's2'];
        foreach ($selectors as $selector) {
            $host    = "{$selector}._domainkey.{$domain}";
            $records = @dns_get_record($host, DNS_TXT) ?: [];
            foreach ($records as $r) {
                $txt = $r['txt'] ?? $r['entries'][0] ?? '';
                if (str_contains($txt, 'v=DKIM1') || str_contains($txt, 'p=')) {
                    return ['found' => true, 'selector' => $selector, 'host' => $host, 'record' => substr($txt, 0, 120)];
                }
            }
        }
        return [
            'found'  => false,
            'record' => null,
            'hint'   => "No DKIM TXT record found at *._domainkey.{$domain}. Check with your email provider for the correct selector.",
        ];
    }

    private function checkDmarc(string $domain): array
    {
        $host    = "_dmarc.{$domain}";
        $records = @dns_get_record($host, DNS_TXT) ?: [];
        foreach ($records as $r) {
            $txt = $r['txt'] ?? $r['entries'][0] ?? '';
            if (str_starts_with($txt, 'v=DMARC1')) {
                return ['found' => true, 'record' => $txt];
            }
        }
        return ['found' => false, 'record' => null, 'hint' => "Add a TXT record to {$host} starting with 'v=DMARC1; p=none;'."];
    }
}

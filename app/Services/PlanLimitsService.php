<?php

namespace App\Services;

use App\Models\ApiClient;
use App\Models\Contact;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\User;

/**
 * Single source of truth for plan limits and feature gates (config/plans.php).
 *
 * Limits are tenant-wide. A null limit means unlimited. Tenants on an active
 * trial get Pro-level limits so they can evaluate the paid product; when the
 * trial lapses they fall back to their stored plan (free unless subscribed).
 */
class PlanLimitsService
{
    /** Plan key whose limits currently apply to the tenant. */
    public function effectivePlanKey(Tenant $tenant): string
    {
        if ($tenant->isTrial() && ! $tenant->trialExpired() && $tenant->plan === 'free') {
            return 'pro';
        }

        $plan = $tenant->plan ?: config('plans.default');

        return array_key_exists($plan, config('plans.plans')) ? $plan : config('plans.default');
    }

    public function planConfig(Tenant $tenant): array
    {
        return config('plans.plans.' . $this->effectivePlanKey($tenant));
    }

    public function label(Tenant $tenant): string
    {
        return $this->planConfig($tenant)['label'];
    }

    /** Limit for a key; null = unlimited. */
    public function limit(Tenant $tenant, string $key): ?int
    {
        $limit = $this->planConfig($tenant)['limits'][$key] ?? null;

        // tenants.max_users acts as a super-admin upward override on the
        // plan's seat count (legacy tenants keep their previously granted
        // seats; new signups start at 1 so the plan drives the limit).
        if ($key === 'users' && $limit !== null) {
            return max($limit, (int) $tenant->max_users);
        }

        return $limit;
    }

    public function hasFeature(Tenant $tenant, string $feature): bool
    {
        return (bool) ($this->planConfig($tenant)['features'][$feature] ?? false);
    }

    public function usage(Tenant $tenant, string $key): int
    {
        return match ($key) {
            'users'           => User::where('tenant_id', $tenant->id)->count(),
            'contacts'        => Contact::where('tenant_id', $tenant->id)->count(),
            'opportunities'   => Opportunity::where('tenant_id', $tenant->id)->count(),
            'email_accounts'  => EmailAccount::where('tenant_id', $tenant->id)->count(),
            'social_accounts' => SocialAccount::where('tenant_id', $tenant->id)->count(),
            'api_clients'     => ApiClient::whereIn('user_id', User::where('tenant_id', $tenant->id)->select('id'))->count(),
            'emails_per_day'  => EmailMessage::where('tenant_id', $tenant->id)
                ->where('direction', 'outbound')
                ->whereDate('created_at', today())
                ->whereIn('status', ['queued', 'sending', 'sent'])
                ->count(),
            default => 0,
        };
    }

    /** Whether the tenant can add $count more of the given resource. */
    public function canAdd(Tenant $tenant, string $key, int $count = 1): bool
    {
        $limit = $this->limit($tenant, $key);

        if ($limit === null) {
            return true;
        }

        return $this->usage($tenant, $key) + $count <= $limit;
    }

    /** Remaining headroom; null = unlimited. */
    public function remaining(Tenant $tenant, string $key): ?int
    {
        $limit = $this->limit($tenant, $key);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->usage($tenant, $key));
    }

    /** Usage vs limits for every metered resource — drives the billing page. */
    public function usageSummary(Tenant $tenant): array
    {
        $summary = [];

        foreach (array_keys($this->planConfig($tenant)['limits']) as $key) {
            $summary[$key] = [
                'used'  => $this->usage($tenant, $key),
                'limit' => $this->limit($tenant, $key),
            ];
        }

        return $summary;
    }

    /** Standard flash message used by enforcement points. */
    public function upgradeMessage(string $resource): string
    {
        $labels = [
            'users'           => 'team members',
            'contacts'        => 'contacts',
            'opportunities'   => 'opportunities',
            'email_accounts'  => 'email accounts',
            'emails_per_day'  => 'emails per day',
            'social_accounts' => 'social accounts',
            'api_clients'     => 'API clients',
        ];

        $label = $labels[$resource] ?? $resource;

        return "You've reached your plan's limit for {$label}. Upgrade your plan to add more.";
    }
}

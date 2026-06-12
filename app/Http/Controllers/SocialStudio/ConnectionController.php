<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialOAuthApp;
use App\Models\SocialProvider;
use App\Services\PlanLimitsService;
use App\Services\Social\LinkedInOAuthException;
use App\Services\Social\LinkedInOAuthService;
use App\Services\Social\WordPressClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ConnectionController extends Controller
{
    public function __construct(
        private LinkedInOAuthService $oauth,
        private WordPressClient $wordPress,
    ) {}

    public function index(Request $request): View
    {
        $user      = $request->user();
        $providers = SocialProvider::all();

        // All configured OAuth apps for this user
        $oauthApps = SocialOAuthApp::where('user_id', $user->id)
            ->with('accounts.provider')
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();

        // All connected accounts grouped by provider key
        $accounts = SocialAccount::where('user_id', $user->id)
            ->with(['provider', 'oauthApp'])
            ->get();

        return view('social-studio.connections', compact('providers', 'oauthApps', 'accounts'));
    }

    public function storeWordPress(Request $request, PlanLimitsService $limits): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        if ($tenant && ! $limits->canAdd($tenant, 'social_accounts')) {
            return back()->withInput()->withErrors(['plan' => $limits->upgradeMessage('social_accounts')]);
        }

        $data = $request->validate([
            'site_url' => ['required', 'url', 'max:500'],
            'label' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'application_password' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $provider = SocialProvider::where('key', 'wordpress')->firstOrFail();
        $siteUrl = rtrim($data['site_url'], '/');

        $account = SocialAccount::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'provider_id' => $provider->id,
            'provider_account_urn' => $siteUrl,
            'display_name' => $data['label'] ?: parse_url($siteUrl, PHP_URL_HOST),
            'public_profile_url' => $siteUrl,
            'access_token_encrypted' => $data['application_password'],
            'status' => 'disconnected',
            'capabilities' => ['html', 'image', 'featured_image'],
            'metadata_json' => [
                'site_url' => $siteUrl,
                'api_base' => "{$siteUrl}/wp-json/wp/v2",
                'username' => $data['username'],
            ],
        ]);

        try {
            $identity = $this->wordPress->verify($account);
            $account->update([
                'status' => 'connected',
                'last_verified_at' => now(),
                'display_name' => $data['label'] ?: (($identity['name'] ?? null) ? "{$identity['name']} ({$siteUrl})" : $account->display_name),
                'metadata_json' => array_merge($account->metadata_json ?? [], [
                    'wp_user_id' => $identity['id'] ?? null,
                    'wp_user_name' => $identity['name'] ?? null,
                ]),
            ]);

            return redirect()->route('social-studio.connections')
                ->with('success', "WordPress site connected: {$account->fresh()->display_name}.");
        } catch (\Throwable $e) {
            $account->update(['status' => 'error']);
            report($e);

            return redirect()->route('social-studio.connections')
                ->with('error', 'WordPress connection failed: ' . $e->getMessage());
        }
    }

    /** Start OAuth for a specific app config. */
    public function connect(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Resolve which OAuth app to use
        $appId = $request->input('app_id');
        $app   = $appId
            ? SocialOAuthApp::where('user_id', $user->id)->findOrFail($appId)
            : SocialOAuthApp::where('user_id', $user->id)->where('is_default', true)->first()
                ?? SocialOAuthApp::where('user_id', $user->id)->first();

        if (! $app) {
            return redirect()->route('social-studio.connections')
                ->with('error', 'No LinkedIn app configured. Add your LinkedIn Developer App credentials first.');
        }

        $state = Str::random(40);
        $request->session()->put('linkedin_oauth_state', $state);
        $request->session()->put('linkedin_oauth_app_id', $app->id);

        return redirect($this->oauth->authorizationUrl($app, $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $storedState = $request->session()->pull('linkedin_oauth_state');
        $appId       = $request->session()->pull('linkedin_oauth_app_id');

        try {
            $this->validateCallbackRequest($request, $storedState, $appId);

            $app       = SocialOAuthApp::where('user_id', $request->user()->id)->findOrFail($appId);
            $tokenData = $this->oauth->exchangeCode($app, $request->input('code'));
            $identity  = $this->oauth->resolveMemberIdentity($tokenData['access_token']);
            $this->oauth->storeConnection($request->user(), $app, $tokenData, $identity);

            return redirect()->route('social-studio.connections')
                ->with('success', "LinkedIn connected as {$identity['display_name']} via \"{$app->label}\".");

        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('social-studio.connections')
                ->with('error', $e->getMessage());
        }
    }

    /** @throws LinkedInOAuthException */
    private function validateCallbackRequest(Request $request, ?string $storedState, mixed $appId): void
    {
        if (! $storedState || ! hash_equals($storedState, (string) $request->input('state'))) {
            throw new LinkedInOAuthException('LinkedIn authorization failed: invalid state. Please try again.');
        }
        if ($request->has('error')) {
            throw new LinkedInOAuthException('LinkedIn authorization declined: ' . $request->input('error_description', 'unknown error'));
        }
        if (! $request->input('code')) {
            throw new LinkedInOAuthException('LinkedIn authorization failed: missing code parameter.');
        }
        if (! $appId) {
            throw new LinkedInOAuthException('OAuth app session expired. Please try connecting again.');
        }
    }

    public function disconnect(Request $request, int $id): RedirectResponse
    {
        $account = SocialAccount::where('user_id', $request->user()->id)
            ->with('provider')
            ->findOrFail($id);

        if ($account->provider?->key === 'wordpress') {
            $account->update([
                'status' => 'disconnected',
                'access_token_encrypted' => null,
            ]);
        } else {
            $this->oauth->disconnect($account);
        }

        return redirect()->route('social-studio.connections')
            ->with('success', ($account->provider?->name ?? 'Social account') . ' disconnected.');
    }

    public function setDefault(Request $request, int $id): RedirectResponse
    {
        $account = SocialAccount::where('user_id', $request->user()->id)->findOrFail($id);
        $account->makeDefault();

        return back()->with('success', "Default account set to {$account->display_name}.");
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        $account = SocialAccount::where('user_id', $request->user()->id)
            ->with(['provider', 'oauthApp'])
            ->findOrFail($id);

        if (! $account->provider->isEnabled()) {
            return back()->with('error', 'This provider is not yet enabled.');
        }

        try {
            if ($account->provider->key === 'wordpress') {
                $identity = $this->wordPress->verify($account);
                $account->update([
                    'status' => 'connected',
                    'last_verified_at' => now(),
                    'metadata_json' => array_merge($account->metadata_json ?? [], [
                        'wp_user_id' => $identity['id'] ?? null,
                        'wp_user_name' => $identity['name'] ?? null,
                    ]),
                ]);
            } else {
                $identity = $this->oauth->resolveMemberIdentity($account->access_token_encrypted);
                $account->update([
                    'status'               => 'connected',
                    'last_verified_at'     => now(),
                    'display_name'         => $identity['display_name'],
                    'provider_account_urn' => $identity['urn'],
                ]);
            }
            return back()->with('success', 'Connection verified successfully.');
        } catch (\Throwable) {
            $account->update(['status' => 'reauthorization_required']);
            return back()->with('error', 'Connection verification failed. Please check the credentials and reconnect.');
        }
    }
}

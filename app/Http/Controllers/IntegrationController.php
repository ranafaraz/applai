<?php

namespace App\Http\Controllers;

use App\Models\ApiClient;
use App\Models\ApiClientToken;
use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function index(Request $request): View
    {
        $clients = ApiClient::where('user_id', $request->user()->id)
            ->with('tokens')
            ->orderByDesc('created_at')
            ->get();

        return view('integrations.index', compact('clients'));
    }

    public function createClient(Request $request, PlanLimitsService $limits): RedirectResponse|View
    {
        $tenant = $request->user()->tenant;
        if ($tenant) {
            if (! $limits->hasFeature($tenant, 'api_access')) {
                return back()->withErrors(['plan' => 'API access is available on the Pro and Team plans. Upgrade to create API clients.']);
            }
            if (! $limits->canAdd($tenant, 'api_clients')) {
                return back()->withErrors(['plan' => $limits->upgradeMessage('api_clients')]);
            }
        }

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'source_type' => 'required|in:custom_gpt,mcp,n8n,internal_agent,other',
            'scopes'      => 'required|array|min:1',
            'scopes.*'    => 'string',
            'expires_at'  => 'nullable|date|after:now',
        ]);

        $client = ApiClient::create([
            'user_id'     => $request->user()->id,
            'name'        => $data['name'],
            'source_type' => $data['source_type'],
            'scopes'      => $data['scopes'],
            'is_active'   => true,
            'expires_at'  => $data['expires_at'] ?? null,
        ]);

        // Auto-generate a first token
        ['raw' => $raw, 'hash' => $hash, 'prefix' => $prefix] = ApiClientToken::generateRaw();

        ApiClientToken::create([
            'api_client_id' => $client->id,
            'user_id'       => $request->user()->id,
            'name'          => 'Default Token',
            'token_hash'    => $hash,
            'token_prefix'  => $prefix,
            'is_active'     => true,
        ]);

        // Return the view directly so the token is passed as a PHP variable — no
        // flash session hop that can silently drop the value on production HTTPS setups.
        // history.replaceState in the view fixes the URL so browser refresh is safe.
        $clients = ApiClient::where('user_id', $request->user()->id)
            ->with('tokens')
            ->orderByDesc('created_at')
            ->get();

        return view('integrations.index', compact('clients'))
            ->with('new_token', $raw)
            ->with('new_client_id', $client->id);
    }

    public function createToken(Request $request, ApiClient $client): View
    {
        abort_unless($client->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'expires_at' => 'nullable|date|after:now',
        ]);

        ['raw' => $raw, 'hash' => $hash, 'prefix' => $prefix] = ApiClientToken::generateRaw();

        ApiClientToken::create([
            'api_client_id' => $client->id,
            'user_id'       => $request->user()->id,
            'name'          => $data['name'],
            'token_hash'    => $hash,
            'token_prefix'  => $prefix,
            'is_active'     => true,
            'expires_at'    => $data['expires_at'] ?? null,
        ]);

        // Return the view directly so the token is passed as a PHP variable — no
        // flash session hop that can silently drop the value on production HTTPS setups.
        // history.replaceState in the view fixes the URL so browser refresh is safe.
        $clients = ApiClient::where('user_id', $request->user()->id)
            ->with('tokens')
            ->orderByDesc('created_at')
            ->get();

        return view('integrations.index', compact('clients'))
            ->with('new_token', $raw)
            ->with('new_client_id', $client->id);
    }

    public function deleteClient(Request $request, ApiClient $client): RedirectResponse
    {
        abort_unless($client->user_id === $request->user()->id, 403);

        $client->delete();

        return redirect()->route('integrations.index')->with('success', 'API client and all its tokens deleted.');
    }

    public function revokeToken(Request $request, ApiClientToken $token): RedirectResponse
    {
        abort_unless($token->user_id === $request->user()->id, 403);

        $token->update(['is_active' => false]);

        return redirect()->route('integrations.index')->with('success', 'Token revoked.');
    }
}

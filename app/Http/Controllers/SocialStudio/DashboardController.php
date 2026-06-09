<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Models\SocialProvider;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $draftsCount     = SocialPost::where('user_id', $user->id)->whereIn('status', ['draft', 'ready_for_review'])->count();
        $scheduledCount  = SocialPost::where('user_id', $user->id)->where('status', 'scheduled')->where('scheduled_at', '>=', now())->where('scheduled_at', '<=', now()->addDays(7))->count();
        $failedCount     = SocialPost::where('user_id', $user->id)->where('status', 'failed')->count();

        $lastPublished = SocialPostTarget::whereHas('post', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', 'published')
            ->latest('published_at')
            ->first();

        $connectedAccounts = SocialAccount::where('user_id', $user->id)
            ->with('provider')
            ->orderBy('status')
            ->orderBy('display_name')
            ->get();

        $connectedCount = $connectedAccounts->where('status', 'connected')->count();
        $connectionGroups = $connectedAccounts->groupBy(fn (SocialAccount $account) => $account->provider?->key ?? 'unknown');

        $providers = SocialProvider::all();

        $recentPublished = SocialPost::where('user_id', $user->id)
            ->where('status', 'published')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return view('social-studio.dashboard', compact(
            'draftsCount', 'scheduledCount', 'failedCount',
            'lastPublished', 'providers', 'recentPublished',
            'connectedAccounts', 'connectedCount', 'connectionGroups'
        ));
    }
}

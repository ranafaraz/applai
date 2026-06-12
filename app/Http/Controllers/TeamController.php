<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSetting;
use App\Services\PlanLimitsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(PlanLimitsService $limits): View
    {
        $tenant    = auth()->user()->tenant;
        $users     = $tenant->users()->latest()->get();
        $userLimit = $limits->limit($tenant, 'users');

        return view('settings.team', compact('tenant', 'users', 'userLimit'));
    }

    public function store(Request $request, PlanLimitsService $limits): RedirectResponse
    {
        $tenant = auth()->user()->tenant;

        if (! $limits->canAdd($tenant, 'users')) {
            return back()->withErrors(['email' => $limits->upgradeMessage('users')]);
        }

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|in:admin,member',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role'      => $data['role'],
            'is_active' => true,
        ]);

        UserSetting::create([
            'user_id'                => $user->id,
            'timezone'               => 'UTC',
            'date_format'            => 'Y-m-d',
            'default_follow_up_days' => 3,
            'notify_on_reply'        => true,
            'notify_on_bounce'       => true,
        ]);

        return back()->with('success', "User {$user->email} added to your team.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $tenant = auth()->user()->tenant;
        abort_if($user->tenant_id !== $tenant->id, 403);
        abort_if($user->id === auth()->id(), 403, "You cannot modify your own role here.");

        $data = $request->validate(['role' => 'required|in:admin,member']);
        $user->update($data);

        return back()->with('success', "{$user->name}'s role updated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $tenant = auth()->user()->tenant;
        abort_if($user->tenant_id !== $tenant->id, 403);
        abort_if($user->id === auth()->id(), 403, "You cannot remove yourself.");

        $user->delete();

        return back()->with('success', "{$user->name} removed from your team.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $tenant = auth()->user()->tenant;
        abort_if($user->tenant_id !== $tenant->id, 403);

        $data = $request->validate(['password' => 'required|string|min:8|confirmed']);
        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', "Password reset for {$user->email}.");
    }
}

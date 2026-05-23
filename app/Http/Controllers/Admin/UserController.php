<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    /** List all users within a tenant (or globally for super admin). */
    public function index(Request $request, ?Tenant $tenant = null): View
    {
        $query = User::with('tenant')->where('role', '!=', 'super_admin');

        if ($tenant && $tenant->exists) {
            $query->where('tenant_id', $tenant->id);
        }

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users', 'tenant'));
    }

    /** Add a new user to a tenant. */
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:admin,member',
        ]);

        $userCount = $tenant->users()->count();
        if ($userCount >= $tenant->max_users) {
            return back()->withErrors(['email' => "This tenant has reached its user limit ({$tenant->max_users})."])->withInput();
        }

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

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', "User {$user->email} added to {$tenant->name}.");
    }

    public function update(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        abort_if($user->tenant_id !== $tenant->id, 403);

        $data = $request->validate([
            'role'      => 'required|in:admin,member',
            'is_active' => 'boolean',
        ]);

        $user->update($data);

        return back()->with('success', "User {$user->email} updated.");
    }

    public function destroy(Tenant $tenant, User $user): RedirectResponse
    {
        abort_if($user->tenant_id !== $tenant->id, 403);

        $user->delete();

        return back()->with('success', "User {$user->email} removed.");
    }

    public function resetPassword(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        abort_if($user->tenant_id !== $tenant->id, 403);

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', "Password reset for {$user->email}.");
    }
}

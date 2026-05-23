<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $query = Tenant::withCount('users');

        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($plan = $request->input('plan')) {
            $query->where('plan', $plan);
        }

        $tenants = $query->latest()->paginate(25)->withQueryString();

        return view('admin.tenants.index', compact('tenants'));
    }

    public function create(): View
    {
        return view('admin.tenants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'nullable|email|max:255',
            'plan'           => 'required|in:free,pro,enterprise',
            'status'         => 'required|in:active,trial,suspended,cancelled',
            'max_users'      => 'required|integer|min:1|max:1000',
            'trial_ends_at'  => 'nullable|date',
            'notes'          => 'nullable|string|max:2000',
            // Admin user for this tenant
            'admin_name'     => 'required|string|max:255',
            'admin_email'    => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        $slug = Str::slug($data['name']);
        $baseSlug = $slug;
        $i = 1;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        $tenant = Tenant::create([
            'name'          => $data['name'],
            'slug'          => $slug,
            'email'         => $data['email'] ?? null,
            'plan'          => $data['plan'],
            'status'        => $data['status'],
            'max_users'     => $data['max_users'],
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name'      => $data['admin_name'],
            'email'     => $data['admin_email'],
            'password'  => Hash::make($data['admin_password']),
            'role'      => 'admin',
            'is_active' => true,
        ]);

        UserSetting::create([
            'user_id'                => $admin->id,
            'timezone'               => 'UTC',
            'date_format'            => 'Y-m-d',
            'default_follow_up_days' => 3,
            'notify_on_reply'        => true,
            'notify_on_bounce'       => true,
        ]);

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', "Tenant \"{$tenant->name}\" created with admin {$admin->email}.");
    }

    public function show(Tenant $tenant): View
    {
        $users = $tenant->users()->latest()->get();
        return view('admin.tenants.show', compact('tenant', 'users'));
    }

    public function edit(Tenant $tenant): View
    {
        return view('admin.tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'nullable|email|max:255',
            'plan'          => 'required|in:free,pro,enterprise',
            'status'        => 'required|in:active,trial,suspended,cancelled',
            'max_users'     => 'required|integer|min:1|max:1000',
            'trial_ends_at' => 'nullable|date',
            'notes'         => 'nullable|string|max:2000',
        ]);

        $tenant->update($data);

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->users()->update(['tenant_id' => null]);
        $tenant->delete();

        return redirect()->route('admin.tenants.index')
            ->with('success', "Tenant \"{$tenant->name}\" deleted.");
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'suspended']);
        return back()->with('success', "Tenant \"{$tenant->name}\" suspended.");
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'active']);
        return back()->with('success', "Tenant \"{$tenant->name}\" activated.");
    }
}

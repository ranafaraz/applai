<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_tenants'    => Tenant::count(),
            'active_tenants'   => Tenant::whereIn('status', ['active', 'trial'])->count(),
            'total_users'      => User::where('role', '!=', 'super_admin')->count(),
            'trial_tenants'    => Tenant::where('status', 'trial')->count(),
            'suspended'        => Tenant::where('status', 'suspended')->count(),
            'pro_plus'         => Tenant::whereIn('plan', ['pro', 'enterprise'])->count(),
        ];

        $recentTenants = Tenant::with('users')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.dashboard', compact('stats', 'recentTenants'));
    }
}

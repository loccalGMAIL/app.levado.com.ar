<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('active', true)->count();
        $inactiveTenants = $totalTenants - $activeTenants;
        $totalUsers = User::count();

        $recentlyActiveTenants = Tenant::where('active', true)
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        $dormantTenants = Tenant::where('active', true)
            ->where('updated_at', '<', now()->subDays(30))
            ->count();

        $recentLogs = AdminAuditLog::with('actor')
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'inactiveTenants',
            'totalUsers',
            'recentlyActiveTenants',
            'dormantTenants',
            'recentLogs',
        ));
    }
}

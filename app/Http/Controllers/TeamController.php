<?php

namespace App\Http\Controllers;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = app(Tenant::class);

        $members = TenantUser::with('user')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get();

        $pendingInvitations = $tenant->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return view('team.index', [
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'roles' => TenantUserRole::cases(),
            'currentUser' => $request->user(),
        ]);
    }

    public function updateRole(Request $request, TenantUser $tenantUser): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_column(TenantUserRole::cases(), 'value'))],
        ]);

        $tenantUser->update(['role' => $validated['role']]);

        return back()->with('status', 'Rol actualizado correctamente.');
    }

    public function deactivate(TenantUser $tenantUser): RedirectResponse
    {
        $tenantUser->update(['active' => false]);

        return back()->with('status', 'Usuario desactivado.');
    }

    public function activate(TenantUser $tenantUser): RedirectResponse
    {
        $tenantUser->update(['active' => true]);

        return back()->with('status', 'Usuario reactivado.');
    }
}

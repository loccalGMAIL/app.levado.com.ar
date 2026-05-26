<?php

namespace App\Http\Controllers;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

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
        abort_unless($tenantUser->tenant_id === app(Tenant::class)->id, 403);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', array_column(TenantUserRole::cases(), 'value'))],
        ]);

        $tenantUser->update(['role' => $validated['role']]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'tenant_user',
            targetId: $tenantUser->id,
            action: 'team_member.role_updated',
            payload: ['user_id' => $tenantUser->user_id, 'role' => $validated['role']],
            tenantId: $tenantUser->tenant_id,
        );

        return back()->with('status', 'Rol actualizado correctamente.');
    }

    public function deactivate(TenantUser $tenantUser): RedirectResponse
    {
        abort_unless($tenantUser->tenant_id === app(Tenant::class)->id, 403);

        $tenantUser->update(['active' => false]);

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'tenant_user',
            targetId: $tenantUser->id,
            action: 'team_member.deactivated',
            payload: ['user_id' => $tenantUser->user_id],
            tenantId: $tenantUser->tenant_id,
        );

        return back()->with('status', 'Usuario desactivado.');
    }

    public function activate(TenantUser $tenantUser): RedirectResponse
    {
        abort_unless($tenantUser->tenant_id === app(Tenant::class)->id, 403);

        $tenantUser->update(['active' => true]);

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'tenant_user',
            targetId: $tenantUser->id,
            action: 'team_member.activated',
            payload: ['user_id' => $tenantUser->user_id],
            tenantId: $tenantUser->tenant_id,
        );

        return back()->with('status', 'Usuario reactivado.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $tenant = app(Tenant::class);
        abort_unless($tenantUser->tenant_id === $tenant->id, 403);

        // Only owner/admin/viewer can be assigned from the team screen — never
        // super_admin (that role is managed exclusively from the backoffice).
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in([
                TenantUserRole::Owner->value,
                TenantUserRole::Admin->value,
                TenantUserRole::Viewer->value,
            ])],
        ]);

        // A user cannot change their own role (prevents self-escalation/lockout).
        abort_if($tenantUser->user_id === $request->user()->id, 403, 'No podés cambiar tu propio rol.');

        // Super admins are never editable from the tenant team screen.
        abort_if($tenantUser->role === TenantUserRole::SuperAdmin, 403);

        // The tenant must always keep at least one active owner.
        if ($tenantUser->role === TenantUserRole::Owner
            && $validated['role'] !== TenantUserRole::Owner->value
            && ! $this->hasOtherActiveOwner($tenant, $tenantUser)) {
            return back()->with('error', 'El equipo debe tener al menos un propietario activo.');
        }

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

    public function deactivate(Request $request, TenantUser $tenantUser): RedirectResponse
    {
        $tenant = app(Tenant::class);
        abort_unless($tenantUser->tenant_id === $tenant->id, 403);

        // Can't deactivate yourself or a super admin from the team screen.
        abort_if($tenantUser->user_id === $request->user()->id, 403, 'No podés desactivarte a vos mismo.');
        abort_if($tenantUser->role === TenantUserRole::SuperAdmin, 403);

        // The tenant must always keep at least one active owner.
        if ($tenantUser->role === TenantUserRole::Owner
            && ! $this->hasOtherActiveOwner($tenant, $tenantUser)) {
            return back()->with('error', 'El equipo debe tener al menos un propietario activo.');
        }

        $tenantUser->update(['active' => false]);

        $this->recorder->record(
            actor: $request->user(),
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

    /**
     * Whether the tenant has another active owner besides the given membership.
     */
    private function hasOtherActiveOwner(Tenant $tenant, TenantUser $tenantUser): bool
    {
        return TenantUser::where('tenant_id', $tenant->id)
            ->where('role', TenantUserRole::Owner->value)
            ->where('active', true)
            ->whereKeyNot($tenantUser->id)
            ->exists();
    }
}

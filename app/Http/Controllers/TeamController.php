<?php

namespace App\Http\Controllers;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AdminActivityRecorder;
use Illuminate\Database\Eloquent\Builder;
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
            ->tap(fn (Builder $q) => $this->sortListing($q, [
                'name' => User::select('name')->whereColumn('users.id', 'tenant_users.user_id'),
                'email' => User::select('email')->whereColumn('users.id', 'tenant_users.user_id'),
                'role' => 'role',
                'active' => 'active',
            ], fn (Builder $q) => $q->orderByDesc('created_at'), 'tenant_users.id'))
            ->get();

        // Las dos tablas de la pantalla se ordenan por separado: las invitaciones
        // usan inv_sort / inv_dir para no pisar el orden de los miembros.
        $pendingInvitations = $tenant->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->tap(fn (Builder $q) => $this->sortListing(
                $q,
                ['email' => 'email', 'role' => 'role', 'expires_at' => 'expires_at'],
                fn (Builder $q) => $q->orderByDesc('created_at'),
                'invitations.id',
                'inv_sort',
                'inv_dir',
            ))
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
        $this->authorize('update', $tenantUser);

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
        $this->authorize('update', $tenantUser);

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
        $this->authorize('update', $tenantUser);

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

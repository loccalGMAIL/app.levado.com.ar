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
            'role' => ['required', Rule::enum(TenantUserRole::class)->except([TenantUserRole::SuperAdmin])],
        ]);

        $newRole = TenantUserRole::from($validated['role']);

        if ($blocked = $this->blockedReason($request->user(), $tenantUser, $newRole)) {
            return back()->with('error', $blocked);
        }

        $tenantUser->update(['role' => $newRole]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'tenant_user',
            targetId: $tenantUser->id,
            action: 'team_member.role_updated',
            payload: ['user_id' => $tenantUser->user_id, 'role' => $newRole->value],
            tenantId: $tenantUser->tenant_id,
        );

        return back()->with('status', 'Rol actualizado correctamente.');
    }

    public function deactivate(Request $request, TenantUser $tenantUser): RedirectResponse
    {
        $this->authorize('update', $tenantUser);

        if ($blocked = $this->blockedReason($request->user(), $tenantUser, null)) {
            return back()->with('error', $blocked);
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

    public function activate(Request $request, TenantUser $tenantUser): RedirectResponse
    {
        $this->authorize('update', $tenantUser);

        if ($blocked = $this->blockedReason($request->user(), $tenantUser, $tenantUser->role)) {
            return back()->with('error', $blocked);
        }

        $tenantUser->update(['active' => true]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'tenant_user',
            targetId: $tenantUser->id,
            action: 'team_member.activated',
            payload: ['user_id' => $tenantUser->user_id],
            tenantId: $tenantUser->tenant_id,
        );

        return back()->with('status', 'Usuario reactivado.');
    }

    /**
     * Jerarquía del equipo: por qué $actor no puede dejar la membresía con
     * $newRole (null = desactivarla). Devuelve null si el cambio está permitido.
     *
     * El rol super_admin no se administra desde acá (sólo desde el backoffice):
     * User::isSuperAdmin() lo toma de cualquier membresía, así que asignarlo
     * desde el equipo de un comercio sería una escalada global.
     */
    private function blockedReason(User $actor, TenantUser $target, ?TenantUserRole $newRole): ?string
    {
        if ($target->role === TenantUserRole::SuperAdmin) {
            return 'Esa membresía sólo se administra desde el backoffice.';
        }

        if ($target->user_id === $actor->id) {
            return 'No podés modificar tu propia membresía.';
        }

        $actorIsOwner = $actor->isSuperAdmin()
            || $actor->hasRoleInTenant($target->tenant()->firstOrFail(), TenantUserRole::Owner);

        if (! $actorIsOwner && ($target->role === TenantUserRole::Owner || $newRole === TenantUserRole::Owner)) {
            return 'Sólo un propietario puede asignar o modificar el rol de propietario.';
        }

        $losesOwner = $target->role === TenantUserRole::Owner && $target->active && $newRole !== TenantUserRole::Owner;

        if ($losesOwner) {
            $otherOwners = TenantUser::where('tenant_id', $target->tenant_id)
                ->where('role', TenantUserRole::Owner->value)
                ->where('active', true)
                ->whereKeyNot($target->id)
                ->exists();

            if (! $otherOwners) {
                return 'El comercio no puede quedarse sin un propietario activo.';
            }
        }

        return null;
    }
}

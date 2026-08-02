<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\TenantUserRole;
use App\Mail\PasswordResetMail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

#[Fillable(['name', 'email', 'password', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function tenantUsers(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function roleInTenant(Tenant $tenant): ?TenantUserRole
    {
        return $this->activeTenantUsers()->firstWhere('tenant_id', $tenant->id)?->role;
    }

    public function hasRoleInTenant(Tenant $tenant, TenantUserRole ...$roles): bool
    {
        $role = $this->roleInTenant($tenant);

        return $role !== null && in_array($role, $roles);
    }

    public function isSuperAdmin(): bool
    {
        return $this->activeTenantUsers()
            ->contains(fn (TenantUser $tenantUser) => $tenantUser->role === TenantUserRole::SuperAdmin);
    }

    /**
     * Membresías activas del usuario, resueltas una sola vez por request.
     *
     * Los gates (manage-costs, edit-settings, …) se evalúan una vez por fila en
     * cada tabla y Laravel no cachea su resultado: con una query por chequeo,
     * un listado de 20 filas se iba a ~80 queries sólo en permisos. La relación
     * cargada hace de cache; `$user->unsetRelation('tenantUsers')` la invalida.
     *
     * @return Collection<int, TenantUser>
     */
    private function activeTenantUsers(): Collection
    {
        $this->loadMissing('tenantUsers');

        return $this->tenantUsers->where('active', true);
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);
        Mail::to($this->email)->send(new PasswordResetMail($this, $url));
    }
}

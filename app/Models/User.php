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
        $tenantUser = $this->tenantUsers()
            ->where('tenant_id', $tenant->id)
            ->where('active', true)
            ->first();

        return $tenantUser?->role;
    }

    public function hasRoleInTenant(Tenant $tenant, TenantUserRole ...$roles): bool
    {
        $role = $this->roleInTenant($tenant);

        return $role !== null && in_array($role, $roles);
    }

    public function isSuperAdmin(): bool
    {
        return $this->tenantUsers()
            ->where('role', TenantUserRole::SuperAdmin->value)
            ->where('active', true)
            ->exists();
    }

    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);
        Mail::to($this->email)->queue(new PasswordResetMail($this, $url));
    }
}

<?php

namespace App\Providers;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('super-admin', function (User $user) {
            $tenant = $this->resolveTenant();

            return $tenant && $user->hasRoleInTenant($tenant, TenantUserRole::SuperAdmin);
        });

        Gate::define('manage-team', function (User $user) {
            $tenant = $this->resolveTenant();

            return $tenant && $user->hasRoleInTenant(
                $tenant,
                TenantUserRole::SuperAdmin,
                TenantUserRole::Owner,
                TenantUserRole::Admin,
            );
        });

        Gate::define('edit-settings', function (User $user) {
            $tenant = $this->resolveTenant();

            return $tenant && $user->hasRoleInTenant(
                $tenant,
                TenantUserRole::SuperAdmin,
                TenantUserRole::Owner,
            );
        });

        Gate::define('manage-costs', function (User $user) {
            $tenant = $this->resolveTenant();

            return $tenant && $user->hasRoleInTenant(
                $tenant,
                TenantUserRole::SuperAdmin,
                TenantUserRole::Owner,
                TenantUserRole::Admin,
            );
        });
    }

    private function resolveTenant(): ?Tenant
    {
        try {
            return app(Tenant::class);
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Providers;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            try {
                $tenant = app(Tenant::class);
                if ($tenant->hasCompletedOnboarding()) {
                    $view->with('onboardingStep', null);

                    return;
                }
                $step = match (true) {
                    $tenant->productive_hours_month === 0 => 0,
                    $tenant->fixedCosts()->count() === 0 => 1,
                    $tenant->laborTypes()->count() === 0 => 2,
                    $tenant->ingredients()->count() === 0 => 3,
                    $tenant->recipes()->count() === 0 => 4,
                    default => null,
                };
                $view->with('onboardingStep', $step);
            } catch (\Throwable) {
                $view->with('onboardingStep', null);
            }
        });

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

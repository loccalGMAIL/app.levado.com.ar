<?php

namespace App\Providers;

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RecipeCostPropagator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton para que RecipeCostPropagator::batch() agrupe también las
        // propagaciones que disparan los servicios inyectados más abajo en el
        // grafo (PurchaseLineRecorder, por ejemplo).
        $this->app->singleton(RecipeCostPropagator::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        View::composer('layouts.app', function ($view) {
            try {
                $tenant = app(Tenant::class);
                if ($tenant->hasCompletedOnboarding()) {
                    $view->with('onboardingStep', null);

                    return;
                }
                $step = match (true) {
                    ! $tenant->productive_hours_month => 0,
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

        Gate::before(function (User $user) {
            if ($user->isSuperAdmin()) {
                return true;
            }
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

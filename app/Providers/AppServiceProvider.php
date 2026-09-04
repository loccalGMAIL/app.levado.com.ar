<?php

namespace App\Providers;

use App\Enums\CatalogItemType;
use App\Enums\DeliveryDestinationType;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::preventLazyLoading();

        // Morph map real (N5 + P5): un solo enforceMorphMap() para los dos
        // discriminadores polimórficos del dominio — llamarlo dos veces pisaría
        // el primero. CatalogItemType (stockable/purchaseable, 'ingredient'/
        // 'packaging'/'product') + DeliveryDestinationType (destino del pedido
        // de producción, 'location'/'delivery_person'). Los valores ya
        // persistidos coinciden con las claves, así que no requiere migración
        // de datos. Desbloquea with()/whereHasMorph().
        Relation::enforceMorphMap(
            collect(CatalogItemType::cases())
                ->mapWithKeys(fn (CatalogItemType $type) => [$type->value => $type->modelClass()])
                ->merge(
                    collect(DeliveryDestinationType::cases())
                        ->mapWithKeys(fn (DeliveryDestinationType $type) => [$type->value => $type->modelClass()])
                )
                ->all(),
        );

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            if (app()->isProduction()) {
                Log::warning('lazy loading violation', ['model' => $model::class, 'relation' => $relation]);

                return;
            }

            throw new LazyLoadingViolationException($model, $relation);
        });

        View::composer('layouts.app', function ($view) {
            try {
                $tenant = app(Tenant::class);
                if ($tenant->hasCompletedOnboarding()) {
                    $view->with('onboardingStep', null);

                    return;
                }

                // hasCompletedOnboarding() ya descartó "tiene recetas": si llegamos
                // acá, recipes()->count() === 0 es siempre true, así que no hace
                // falta pedirlo — 3 EXISTS en un viaje en vez de 4 COUNT en cuatro.
                $flags = $tenant->newQuery()
                    ->whereKey($tenant->id)
                    ->withExists(['fixedCosts', 'laborTypes', 'ingredients'])
                    ->first();

                $step = match (true) {
                    ! $tenant->productive_hours_month => 0,
                    ! $flags->fixed_costs_exists => 1,
                    ! $flags->labor_types_exists => 2,
                    ! $flags->ingredients_exists => 3,
                    default => 4,
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

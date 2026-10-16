<?php

use App\Enums\TenantUserRole;
use App\Models\FixedCost;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionOrder;
use App\Models\Purchase;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\VariableExpense;

/**
 * Los listados migrados a x-data-table renderizan cada registro una sola vez
 * (antes card + fila con x-responsive-table), con los íconos del sprite y sin un
 * popover teletransportado por fila.
 */
function userForListings(TenantUserRole $role = TenantUserRole::Owner): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

dataset('migrated listings', [
    'listas de precios' => ['price-lists.index', fn (Tenant $t) => PriceList::factory()->for($t)->count(3)->create()],
    'envases' => ['packaging.index', fn (Tenant $t) => Packaging::factory()->for($t)->count(3)->create()],
    'gastos fijos' => ['fixed-costs.index', fn (Tenant $t) => FixedCost::factory()->for($t)->count(3)->create()],
    'gastos variables' => ['variable-expenses.index', fn (Tenant $t) => VariableExpense::factory()->for($t)->count(3)->create()],
    'stock' => ['stock.index', fn (Tenant $t) => Ingredient::factory()->for($t)->count(3)->create()],
    'artículos' => ['products.index', fn (Tenant $t) => Product::factory()->for($t)->count(3)->create()],
    'compras' => ['purchases.index', fn (Tenant $t) => Purchase::factory()->for($t)->count(3)->create()],
    'órdenes de producción' => ['production-orders.index', fn (Tenant $t) => ProductionOrder::factory()->for($t)->count(3)->create()],
    'historial de producción' => ['products.history', fn (Tenant $t) => Production::factory()->for($t)->count(3)->create()],
]);

test('cada registro es una sola fila, sin el doble render de card + tabla', function (string $routeName, Closure $seed) {
    [$user, $tenant] = userForListings();
    $seed($tenant);

    $html = $this->actingAs($user)->get(route($routeName))->assertOk()->getContent();

    $titles = substr_count($html, 'data-role="title"');

    // La lista de precios por defecto del tenant también cuenta como registro.
    expect($titles)->toBeGreaterThanOrEqual(3)
        ->and($titles)->toBeLessThanOrEqual(4)
        ->and($html)->toContain('class="data-table"')
        ->and($html)->not->toContain("mobileExpanded ? 'hidden' : 'md:hidden'")
        ->and($html)->not->toContain('x-teleport');
})->with('migrated listings');

test('los íconos de las acciones salen del sprite y no se repiten como path inline', function (string $routeName, Closure $seed) {
    [$user, $tenant] = userForListings();
    $seed($tenant);

    $html = $this->actingAs($user)->get(route($routeName))->assertOk()->getContent();

    // Los paths de edición y de desactivar viven una sola vez en public/icons/sprite.svg.
    expect($html)->not->toContain('M16.862 4.487l1.687-1.688')
        ->and($html)->not->toContain('M18.364 18.364A9 9 0 005.636 5.636');
})->with('migrated listings');

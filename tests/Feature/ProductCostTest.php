<?php

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\Recipe;
use App\Services\StockService;

// tenantUserAs() es un helper global de la suite (definido en IngredientCrudTest).

/** Elaborado con una receta de costo conocido. */
function manufacturedWithCost($tenant, ?float $unitCost): Product
{
    $recipe = Recipe::factory()->for($tenant)->create([
        'unit_cost' => $unitCost,
        'yield_quantity' => 12,
        'yield_unit' => Unit::Unidad->value,
    ]);

    return Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
    ]);
}

test('el costo vigente de un elaborado sale de la receta', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = manufacturedWithCost($tenant, 15);

    expect($product->currentCost())->toBe(15.0)
        ->and($product->currentCostSource())->toBe('receta');
});

test('el costo vigente de un producto de reventa sale de su cost_per_unit', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 42.5]);

    expect($product->currentCost())->toBe(42.5)
        ->and($product->currentCostSource())->toBe('compra');
});

test('el costo vigente es null cuando el elaborado todavía no tiene costo de receta', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = manufacturedWithCost($tenant, null);

    expect($product->currentCost())->toBeNull();
});

test('el costo vigente es null cuando un producto de reventa no tiene costo', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => null]);

    expect($product->currentCost())->toBeNull();
});

test('el tab Productos de /stock valúa el elaborado con el costo de la receta', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['unit_cost' => 15, 'yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $product = Product::factory()->for($tenant)->create([
        'name' => 'DocenaTest',
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
    ]);
    app(StockService::class)->registerAdjustment($product, $tenant->defaultLocation(), 3, 'Inicial', $user);

    $this->actingAs($user)
        ->get(route('stock.index', ['type' => 'product']))
        ->assertOk()
        ->assertSee('DocenaTest')
        ->assertSee('45,00'); // 3 × 15, antes valuaba 0
});

<?php

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Services\ProductPriceWriter;

// tenantUserAs() es un helper global de la suite (definido en IngredientCrudTest).

test('editar el precio de un artículo escribe product_prices y devuelve el margen', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 40]);
    $list = $tenant->defaultPriceList();

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => 100])
        ->assertOk()
        ->assertJsonPath('selling_price_formatted', '100,00')
        ->assertJsonPath('margin_formatted', '60,00')       // 100 - 40
        ->assertJsonPath('margin_pct_formatted', '60,0');   // 60 / 100

    expect((float) $product->currentPrice($list))->toBe(100.0);
});

test('borrar el precio (null) elimina el product_price', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 40]);
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->set($product, $list, 100);

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => null])
        ->assertOk()
        ->assertJsonPath('selling_price', null);

    expect($product->currentPrice($list))->toBeNull();
});

test('el margen del elaborado usa el costo total del artículo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    // sin gastos fijos → overhead 0 → fullCost = unit_cost de la receta
    $recipe = Recipe::factory()->for($tenant)->create(['unit_cost' => 10, 'labor_hours' => 0, 'yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $product = Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
    ]);
    $list = $tenant->defaultPriceList();

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['price' => 25])
        ->assertOk()
        ->assertJsonPath('margin_pct_formatted', '60,0'); // (25 - 10) / 25
});

test('aislamiento: no se puede precisar un artículo de otro negocio', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);
    $other = Tenant::factory()->create();
    $foreign = Product::factory()->for($other)->resale()->create();
    $list = $other->defaultPriceList();

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$foreign, $list]), ['price' => 100])
        ->assertNotFound(); // route-model binding scopeado por tenant
});

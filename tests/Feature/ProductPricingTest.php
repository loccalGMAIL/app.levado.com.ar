<?php

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\Recipe;
use App\Services\ProductPriceWriter;
use App\Services\RecipePriceWriter;

// tenantUserAs() es un helper global de la suite (definido en IngredientCrudTest).

test('currentPrice devuelve el precio del artículo en una lista', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 50]);
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->set($product, $list, 120);

    expect($product->currentPrice($list))->toBe(120.0);
});

test('currentPrice es null cuando el artículo no tiene precio en la lista', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create();

    expect($product->currentPrice($tenant->defaultPriceList()))->toBeNull();
});

test('fullCost del elaborado suma el prorrateo de overhead al costo directo', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['unit_cost' => 10, 'labor_hours' => 2, 'yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $product = Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
    ]);

    // overhead/hora = 30 → prorrateo = 2 × 30 / 12 = 5 → fullCost = 10 + 5
    expect($product->fullCost(30))->toBe(15.0)
        ->and($product->currentCost())->toBe(10.0);
});

test('fullCost de la reventa es el costo directo (sin overhead)', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 80]);

    expect($product->fullCost(999))->toBe(80.0);
});

test('fullCost es null si el elaborado todavía no tiene costo de receta', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['unit_cost' => null]);
    $product = Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
    ]);

    expect($product->fullCost(30))->toBeNull();
});

test('el catálogo de Artículos muestra costo, precio y margen del elaborado (precio de la receta)', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['unit_cost' => 10, 'labor_hours' => 0, 'yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $product = Product::factory()->for($tenant)->create([
        'name' => 'FacturasTest',
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
    ]);
    // El elaborado lee su precio de la receta (misma fuente que el Dashboard).
    app(RecipePriceWriter::class)->set($recipe, $tenant->defaultPriceList(), 25);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('FacturasTest')
        ->assertSee('25,00')   // precio (de la receta)
        ->assertSee('60,0%');  // margen (25 - 10) / 25
});

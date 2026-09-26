<?php

use App\Enums\PricingPolicy;
use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Services\ArticlePriceRecalculator;
use App\Services\ProductPriceWriter;
use App\Services\RecipeCostPropagator;

// tenantUserAs() (IngredientCrudTest), manufacturedProduct() (ProductionTest) y
// propagateRecipeCosts() (helper global) están definidos en la suite.

test('PricingPolicy calcula el precio por margen y por recargo', function () {
    expect(PricingPolicy::Margin->priceFor(60, 40))->toBe(100.0)     // 60 / (1 - 0.40)
        ->and(PricingPolicy::Markup->priceFor(100, 40))->toBe(140.0) // 100 × 1.40
        ->and(PricingPolicy::Margin->priceFor(100, 100))->toBeNull() // margen 100% inválido
        ->and(PricingPolicy::Manual->priceFor(100, 40))->toBeNull()
        ->and(PricingPolicy::Margin->priceFor(null, 40))->toBeNull();
});

test('setPolicy sobre una reventa computa y cachea el precio', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 60]);
    $list = $tenant->defaultPriceList();

    app(ProductPriceWriter::class)->setPolicy($product, $list, PricingPolicy::Margin, 40);

    expect((float) $product->currentPrice($list))->toBe(100.0);
});

test('el precio con política se recalcula al cambiar el costo de reventa', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 60]);
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($product, $list, PricingPolicy::Markup, 50);
    expect((float) $product->currentPrice($list))->toBe(90.0); // 60 × 1.5

    $product->update(['cost_per_unit' => 100]);
    app(ArticlePriceRecalculator::class)->recompute($product);

    expect((float) $product->fresh()->currentPrice($list))->toBe(150.0); // 100 × 1.5
});

test('editar el costo de reventa a mano recalcula el precio con política', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create([
        'name' => 'Gaseosa',
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => 100,
    ]);
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($product, $list, PricingPolicy::Margin, 50);
    expect((float) $product->currentPrice($list))->toBe(200.0); // 100 / (1 - 0.50)

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => 'Gaseosa',
            'type' => $product->type->value,
            'unit' => $product->unit->value,
            'cost_per_unit' => 200,
        ])
        ->assertRedirect();

    expect((float) $product->fresh()->currentPrice($list))->toBe(400.0);
});

test('guardar un artículo sin tocar el costo no mueve el precio con política', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create([
        'name' => 'Gaseosa',
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => 100,
    ]);
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($product, $list, PricingPolicy::Margin, 50);

    // El precio cacheado quedó adrede fuera de la política: sólo se recomputa si
    // el costo se movió, no en cada guardado.
    $product->prices()->where('price_list_id', $list->id)->update(['price' => 111]);

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => 'Gaseosa renombrada',
            'type' => $product->type->value,
            'unit' => $product->unit->value,
            'cost_per_unit' => 100,
        ])
        ->assertRedirect();

    expect((float) $product->fresh()->currentPrice($list))->toBe(111.0);
});

test('pasar un artículo de reventa a elaborado recalcula el precio contra el costo de la receta', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create([
        'name' => 'Budín',
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => 100,
    ]);
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($product, $list, PricingPolicy::Margin, 50);
    expect((float) $product->currentPrice($list))->toBe(200.0);

    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Unidad->value,
        'unit_cost' => 30,
    ]);

    // normalizeByType() nulea cost_per_unit: el costo cambia de origen aunque la
    // columna editada no sea el costo.
    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => 'Budín',
            'type' => ProductType::Manufactured->value,
            'recipe_id' => $recipe->id,
            'unit' => $product->unit->value,
        ])
        ->assertRedirect();

    expect((float) $product->fresh()->currentPrice($list))->toBe(60.0); // 30 / (1 - 0.50)
});

test('el precio con política de un elaborado se recalcula al propagar el costo de su receta', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Unidad->value, 'cost_per_unit' => 10]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $ingredient->id, 'quantity' => 1, 'unit' => Unit::Unidad->value]);
    propagateRecipeCosts($recipe); // unit_cost = 10

    $product = manufacturedProduct($tenant, $recipe);
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($product, $list, PricingPolicy::Markup, 100);
    expect((float) $product->currentPrice($list))->toBe(20.0); // 10 × 2

    // Sube el costo del insumo → propaga a la receta → recomputa el precio del artículo.
    $ingredient->update(['cost_per_unit' => 30]);
    app(RecipeCostPropagator::class)->propagateFromIngredient($ingredient->id);

    expect((float) $product->fresh()->currentPrice($list))->toBe(60.0); // 30 × 2
});

test('el endpoint acepta una política de margen y computa el precio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 60]);
    $list = $tenant->defaultPriceList();

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $list]), ['policy_type' => 'margin', 'policy_value' => 40])
        ->assertOk()
        ->assertJsonPath('policy_type', 'margin')
        ->assertJsonPath('selling_price_formatted', '100,00');

    expect((float) $product->currentPrice($list))->toBe(100.0);
});

test('products:refresh-prices recalcula los precios con política', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 60]);
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($product, $list, PricingPolicy::Markup, 50); // cache = 90

    $product->updateQuietly(['cost_per_unit' => 100]); // cambia el costo sin disparar el trigger

    $this->artisan('products:refresh-prices')->assertSuccessful();

    expect((float) $product->fresh()->currentPrice($list))->toBe(150.0);
});

<?php

use App\Enums\PricingPolicy;
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

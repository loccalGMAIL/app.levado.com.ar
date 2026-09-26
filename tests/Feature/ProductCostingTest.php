<?php

use App\Enums\CostingMethod;
use App\Enums\TenantUserRole;
use App\Models\Product;
use App\Models\Recipe;
use App\Services\StockService;

// stockPurchaseOwner(), stockPurchaseFor(), stockLineFor() y lineRecorder() son
// helpers globales (definidos en StockPurchaseIntegrationTest).

/** Compra de $qty unidades del producto a $unitPrice/u, ya imputada. */
function buyResale(Product $product, $tenant, float $qty, float $unitPrice): void
{
    $line = stockLineFor(stockPurchaseFor($tenant), [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'quantity_purchased' => $qty,
        'purchase_unit' => 'u',
        'unit_price' => $unitPrice,
    ]);
    lineRecorder()->apply($line);
}

test('effectiveCostingMethod usa el override del producto o el default del negocio', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create();

    expect($product->effectiveCostingMethod(CostingMethod::LastCost))->toBe(CostingMethod::LastCost);

    $product->update(['costing_method' => CostingMethod::WeightedAverage->value]);
    expect($product->fresh()->effectiveCostingMethod(CostingMethod::LastCost))->toBe(CostingMethod::WeightedAverage);
});

test('con último costo la compra fija el costo de la compra', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $tenant->setSetting('resale.costing_method', CostingMethod::LastCost->value);
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);
    app(StockService::class)->registerAdjustment($product, $tenant->defaultLocation(), 10, 'inicial', $user);

    buyResale($product, $tenant, 10, 200);

    expect((float) $product->refresh()->cost_per_unit)->toBe(200.0);
});

test('con promedio ponderado la compra promedia contra el stock existente', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $tenant->setSetting('resale.costing_method', CostingMethod::WeightedAverage->value);
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);
    app(StockService::class)->registerAdjustment($product, $tenant->defaultLocation(), 10, 'inicial', $user);

    buyResale($product, $tenant, 10, 200);

    // (10×100 + 10×200) / 20 = 150
    expect((float) $product->refresh()->cost_per_unit)->toBe(150.0);
});

test('el override por artículo pisa el método del negocio', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $tenant->setSetting('resale.costing_method', CostingMethod::LastCost->value); // negocio: último
    $product = Product::factory()->for($tenant)->resale()->create([
        'unit' => 'u', 'cost_per_unit' => 100, 'costing_method' => CostingMethod::WeightedAverage->value,
    ]);
    app(StockService::class)->registerAdjustment($product, $tenant->defaultLocation(), 10, 'inicial', $user);

    buyResale($product, $tenant, 10, 200);

    expect((float) $product->refresh()->cost_per_unit)->toBe(150.0); // promedio, por el override
});

test('con promedio y sin stock previo el costo es el de la compra', function () {
    [, $tenant] = stockPurchaseOwner();
    $tenant->setSetting('resale.costing_method', CostingMethod::WeightedAverage->value);
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 0]);

    buyResale($product, $tenant, 10, 200);

    expect((float) $product->refresh()->cost_per_unit)->toBe(200.0);
});

test('crear un producto de reventa guarda el override de método de costeo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)->post(route('products.store'), [
        'name' => 'Gaseosa', 'type' => 'resale', 'unit' => 'u', 'cost_per_unit' => 100,
        'costing_method' => CostingMethod::WeightedAverage->value,
    ])->assertRedirect();

    expect($tenant->products()->where('name', 'Gaseosa')->first()->costing_method)->toBe(CostingMethod::WeightedAverage);
});

test('un elaborado ignora el método de costeo', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)->post(route('products.store'), [
        'name' => 'Docena', 'type' => 'manufactured', 'recipe_id' => $recipe->id, 'unit' => 'u',
        'costing_method' => CostingMethod::WeightedAverage->value,
    ])->assertRedirect();

    expect($tenant->products()->where('name', 'Docena')->first()->costing_method)->toBeNull();
});

test('Mi negocio guarda el método de costeo por defecto', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)->patch(route('business.update'), [
        'name' => $tenant->name, 'country' => 'AR', 'currency' => 'ARS',
        'productive_hours_month' => 160, 'purchase_price_includes_iva' => '1',
        'resale_costing_method' => CostingMethod::WeightedAverage->value,
    ])->assertRedirect();

    expect($tenant->fresh()->getSetting('resale.costing_method'))->toBe(CostingMethod::WeightedAverage->value);
});

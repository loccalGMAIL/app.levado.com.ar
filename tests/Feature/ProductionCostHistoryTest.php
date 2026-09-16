<?php

use App\Enums\CostLogSource;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCostLog;
use App\Models\Recipe;
use App\Models\Tenant;

// stockTenantUser() es global (StockServiceTest); manufacturedProduct() y
// seedStock() son globales (ProductionTest); productionService() es global (ProductionTest).

test('el historial de un elaborado lista sus producciones de la más reciente a la más vieja', function () {
    [$user, $tenant] = stockTenantUser();
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => 'gr', 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 10, 'yield_unit' => 'u']);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 100, 'unit' => 'gr']);
    $product = manufacturedProduct($tenant, $recipe);

    $first = productionService()->produce($product, 10, null, $user);
    $first->update(['produced_at' => now()->subDay()]);
    $second = productionService()->produce($product, 20, null, $user);

    $json = $this->actingAs($user)
        ->getJson(route('products.cost-history', $product))
        ->assertOk()
        ->json();

    expect($json['is_resale'])->toBeFalse()
        ->and($json['rows'])->toHaveCount(2)
        ->and($json['rows'][0]['production_label'])->toContain('20,00')
        ->and($json['rows'][1]['production_label'])->toContain('10,00');
});

test('el historial de un elaborado marca las producciones anuladas', function () {
    [$user, $tenant] = stockTenantUser();
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => 'gr', 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 10, 'yield_unit' => 'u']);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 100, 'unit' => 'gr']);
    $product = manufacturedProduct($tenant, $recipe);

    $production = productionService()->produce($product, 10, null, $user);
    productionService()->cancel($production->fresh(), $user);

    $json = $this->actingAs($user)
        ->getJson(route('products.cost-history', $product))
        ->assertOk()
        ->json();

    expect($json['rows'][0]['cancelled'])->toBeTrue();
});

test('el historial de un elaborado enlaza a cada producción', function () {
    [$user, $tenant] = stockTenantUser();
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => 'gr', 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 10, 'yield_unit' => 'u']);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 100, 'unit' => 'gr']);
    $product = manufacturedProduct($tenant, $recipe);

    $production = productionService()->produce($product, 10, null, $user);

    $json = $this->actingAs($user)
        ->getJson(route('products.cost-history', $product))
        ->assertOk()
        ->json();

    expect($json['rows'][0]['production_url'])->toBe(route('production.show', $production))
        ->and($json['rows'][0]['purchase_url'])->toBeNull();
});

test('el historial de una reventa sigue saliendo de sus logs de costo', function () {
    [$user, $tenant] = stockTenantUser();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);
    ProductCostLog::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'cost_per_unit' => 100,
        'source' => CostLogSource::Manual->value,
        'recorded_at' => now(),
    ]);

    $json = $this->actingAs($user)
        ->getJson(route('products.cost-history', $product))
        ->assertOk()
        ->json();

    expect($json['is_resale'])->toBeTrue()
        ->and($json['rows'])->toHaveCount(1)
        ->and($json['rows'][0]['source'])->toBe('manual')
        ->and($json['rows'][0]['production_url'])->toBeNull();
});

test('el historial de un elaborado sin producciones vuelve vacío', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 10, 'yield_unit' => 'u']);
    $product = manufacturedProduct($tenant, $recipe);

    $this->actingAs($user)
        ->getJson(route('products.cost-history', $product))
        ->assertOk()
        ->assertJsonPath('rows', []);
});

test('el historial de fabricaciones respeta el aislamiento entre negocios', function () {
    [$user] = stockTenantUser();
    $otherTenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($otherTenant)->create(['yield_quantity' => 10, 'yield_unit' => 'u']);
    $foreign = manufacturedProduct($otherTenant, $recipe);

    $this->actingAs($user)
        ->getJson(route('products.cost-history', $foreign))
        ->assertNotFound();
});

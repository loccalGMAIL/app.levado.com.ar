<?php

use App\Enums\CostLogSource;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductCostLog;
use App\Models\Recipe;
use App\Models\Tenant;

// stockPurchaseOwner(), stockPurchaseFor(), stockLineFor() y lineRecorder() están
// en StockPurchaseIntegrationTest; buyResale() en ProductCostingTest;
// tenantUserAs() en IngredientCrudTest.

// --- Etiqueta de origen en el catálogo ---

test('el catálogo etiqueta al elaborado como Receta y a la reventa como Compra', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $recipe = Recipe::factory()->for($tenant)->create(['unit_cost' => 30, 'yield_quantity' => 1]);
    Product::factory()->for($tenant)->manufactured()->create(['name' => 'BudinElaborado', 'recipe_id' => $recipe->id]);
    Product::factory()->for($tenant)->resale()->create(['name' => 'GaseosaReventa', 'unit' => 'u', 'cost_per_unit' => 100]);

    $html = $this->actingAs($user)->get(route('products.index'))->assertOk()->getContent();

    expect($html)->toContain('El costo lo calcula la receta')
        ->toContain('El costo lo alimentan las compras');
});

test('un costo tipeado a mano se etiqueta Manual, no Compra', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);
    ProductCostLog::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'source' => CostLogSource::Manual->value,
        'recorded_at' => now(),
    ]);

    $html = $this->actingAs($user)->get(route('products.index'))->assertOk()->getContent();

    expect($html)->toContain('Costo cargado a mano');
});

test('el último log manda sobre los anteriores', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);
    ProductCostLog::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'source' => CostLogSource::Manual->value,
        'recorded_at' => now()->subDay(),
    ]);
    ProductCostLog::factory()->fromPurchase()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'recorded_at' => now(),
    ]);

    $html = $this->actingAs($user)->get(route('products.index'))->assertOk()->getContent();

    expect($html)->toContain('Costo imputado desde una factura')
        ->not->toContain('Costo cargado a mano');
});

// --- Endpoint de historial ---

test('el historial devuelve los movimientos de costo del más nuevo al más viejo', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);

    $this->actingAs($user);
    buyResale($product, $tenant, 1, 150);

    ProductCostLog::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'cost_per_unit' => 100,
        'source' => CostLogSource::Manual->value,
        'recorded_at' => now()->subDay(),
    ]);

    $json = $this->getJson(route('products.cost-history', $product))->assertOk()->json();

    expect($json['rows'])->toHaveCount(2)
        ->and($json['rows'][0]['source'])->toBe('compra')
        ->and((float) $json['rows'][0]['cost'])->toBe(150.0)
        ->and($json['rows'][0]['cost_formatted'])->toBe('150,00')
        ->and($json['rows'][0]['purchase_url'])->not->toBeNull()
        ->and($json['rows'][1]['source'])->toBe('manual')
        ->and($json['rows'][1]['purchase_url'])->toBeNull();
});

test('un artículo sin movimientos devuelve un historial vacío', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);

    $this->actingAs($user)
        ->getJson(route('products.cost-history', $product))
        ->assertOk()
        ->assertJsonPath('rows', []);
});

test('aislamiento: no se puede ver el historial de un artículo de otro negocio', function () {
    [$user] = stockPurchaseOwner();
    $foreign = Product::factory()->for(Tenant::factory()->create())->resale()->create();

    // El scope de tenant lo vuelve invisible: el route-model binding da 404.
    $this->actingAs($user)
        ->getJson(route('products.cost-history', $foreign))
        ->assertNotFound();
});

test('el historial exige estar autenticado', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create();

    $this->getJson(route('products.cost-history', $product))->assertUnauthorized();
});

// --- Stock ---

test('la ficha de stock de un elaborado muestra el origen Receta', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['unit_cost' => 30, 'yield_quantity' => 1]);
    $product = Product::factory()->for($tenant)->manufactured()->create([
        'unit' => Unit::Unidad->value,
        'recipe_id' => $recipe->id,
    ]);

    $html = $this->actingAs($user)
        ->get(route('stock.show', ['type' => 'product', 'id' => $product->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('El costo lo calcula la receta');
});

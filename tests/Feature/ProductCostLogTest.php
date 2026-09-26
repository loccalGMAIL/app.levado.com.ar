<?php

use App\Enums\CatalogItemType;
use App\Enums\CostingMethod;
use App\Enums\CostLogSource;
use App\Enums\ProductType;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductCostLog;
use App\Models\Recipe;
use App\Services\StockService;
use Illuminate\Support\Facades\Schema;

// stockPurchaseOwner(), stockPurchaseFor(), stockLineFor() y lineRecorder() están
// en StockPurchaseIntegrationTest; buyResale() en ProductCostingTest.

// --- Origen compra ---

test('comprar un artículo de reventa registra un log con origen compra', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);

    buyResale($product, $tenant, 1, 150);

    $log = ProductCostLog::withoutGlobalScopes()->firstOrFail();

    expect((float) $log->cost_per_unit)->toBe(150.0)
        ->and($log->source)->toBe(CostLogSource::Purchase)
        ->and($log->product_id)->toBe($product->id)
        ->and($log->purchase_line_id)->not->toBeNull();
});

test('re-imputar la misma línea actualiza el log en vez de duplicarlo', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);

    buyResale($product, $tenant, 1, 150);
    lineRecorder()->apply($tenant->purchases()->latest('id')->firstOrFail()->lines()->firstOrFail());

    expect(ProductCostLog::withoutGlobalScopes()->count())->toBe(1);
});

test('el log de compra registra el costo final, no el de la factura', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create([
        'unit' => 'u',
        'cost_per_unit' => 100,
        'costing_method' => CostingMethod::WeightedAverage->value,
    ]);
    app(StockService::class)->registerAdjustment($product, $tenant->defaultLocation(), 90, 'inicial', $user);

    buyResale($product, $tenant, 10, 200);

    // (90 × 100 + 10 × 200) / 100 = 110, no los 200 de la factura.
    expect((float) ProductCostLog::withoutGlobalScopes()->firstOrFail()->cost_per_unit)->toBe(110.0);
});

// --- Origen manual ---

test('crear un artículo de reventa registra su costo inicial como manual', function () {
    [$user] = stockPurchaseOwner();

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Gaseosa',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => 80,
        ])
        ->assertRedirect();

    $log = ProductCostLog::withoutGlobalScopes()->firstOrFail();

    expect((float) $log->cost_per_unit)->toBe(80.0)
        ->and($log->source)->toBe(CostLogSource::Manual)
        ->and($log->purchase_line_id)->toBeNull();
});

test('editar el costo a mano registra un log manual', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create([
        'name' => 'Gaseosa',
        'unit' => 'u',
        'cost_per_unit' => 80,
    ]);

    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => 'Gaseosa',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => 95,
        ])
        ->assertRedirect();

    $log = ProductCostLog::withoutGlobalScopes()->firstOrFail();

    expect((float) $log->cost_per_unit)->toBe(95.0)
        ->and($log->source)->toBe(CostLogSource::Manual);
});

test('guardar el artículo sin cambiar el costo no genera un log', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create([
        'name' => 'Gaseosa',
        'unit' => 'u',
        'cost_per_unit' => 80,
    ]);

    // El costo llega igual en cada guardado: el request lo exige para reventa.
    $this->actingAs($user)
        ->put(route('products.update', $product), [
            'name' => 'Gaseosa renombrada',
            'type' => ProductType::Resale->value,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => 80,
        ])
        ->assertRedirect();

    expect(ProductCostLog::withoutGlobalScopes()->count())->toBe(0);
});

// --- Qué queda afuera ---

test('un artículo elaborado no registra logs de costo', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Unidad->value,
        'unit_cost' => 30,
    ]);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Budín',
            'type' => ProductType::Manufactured->value,
            'recipe_id' => $recipe->id,
            'unit' => Unit::Unidad->value,
        ])
        ->assertRedirect();

    expect(ProductCostLog::withoutGlobalScopes()->count())->toBe(0);
});

// --- Forma de la tabla ---

test('los logs de costo son inmutables: no llevan updated_at', function () {
    $log = ProductCostLog::factory()->create();

    expect($log->timestamps)->toBeFalse()
        ->and(Schema::hasColumn('product_cost_logs', 'updated_at'))->toBeFalse()
        ->and(Schema::hasColumn('product_cost_logs', 'recorded_at'))->toBeTrue();
});

test('el log de compra lleva tenant_id aunque no haya tenant bindeado', function () {
    // Sin actingAs: contexto artisan/test, donde BelongsToTenant no completa nada.
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 100]);

    buyResale($product, $tenant, 1, 150);

    $log = ProductCostLog::withoutGlobalScopes()->firstOrFail();

    expect($log->tenant_id)->toBe($tenant->id)
        ->and($log->product->stockLevels()->first()?->stockable_type)->toBe(CatalogItemType::Product->value);
});

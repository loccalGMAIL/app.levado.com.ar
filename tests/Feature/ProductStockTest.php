<?php

use App\Enums\CatalogItemType;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Tenant;

// stockTenantUser() y stockService() son helpers globales (definidos en StockServiceTest).

// --- StockService sobre productos ---

test('un ajuste sobre un producto de reventa suma stock y marca el tipo product', function () {
    [$user, $tenant] = stockTenantUser();
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 100]);
    $location = $tenant->defaultLocation();

    $movement = stockService()->registerAdjustment($product, $location, 5, 'Carga inicial', $user);

    expect($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->stockable_type)->toBe(CatalogItemType::Product->value)
        ->and($movement->isProduct())->toBeTrue()
        ->and((float) $movement->quantity)->toBe(5.0)
        ->and((float) $movement->unit_cost)->toBe(100.0);

    $level = stockService()->levelFor($product, $location);
    expect((float) $level->quantity)->toBe(5.0);
});

test('un recuento sobre un producto registra la diferencia', function () {
    [$user, $tenant] = stockTenantUser();
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 50]);
    $location = $tenant->defaultLocation();

    stockService()->registerAdjustment($product, $location, 10, 'Inicial', $user);
    $count = stockService()->applyCount($product, $location, 8, $user);

    expect((float) $count->quantity)->toBe(-2.0)
        ->and($count->type)->toBe(StockMovementType::Count)
        ->and((float) stockService()->levelFor($product, $location)->quantity)->toBe(8.0);
});

test('las relaciones stockLevels y stockMovements del producto quedan acotadas al tipo product', function () {
    [$user, $tenant] = stockTenantUser();
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 10]);
    $location = $tenant->defaultLocation();

    stockService()->registerAdjustment($product, $location, 3, 'Inicial', $user);

    expect($product->stockMovements()->count())->toBe(1)
        ->and($product->stockLevels()->count())->toBe(1)
        ->and((float) $product->stockLevels()->first()->quantity)->toBe(3.0);
});

// --- UI de Stock: pestaña Productos ---

test('la pestaña Productos del stock lista los productos activos', function () {
    [$user, $tenant] = stockTenantUser();
    Product::factory()->for($tenant)->resale()->create(['name' => 'ProductoStock']);

    $this->actingAs($user)
        ->get(route('stock.index', ['type' => 'product']))
        ->assertOk()
        ->assertSee('ProductoStock')
        ->assertSee('Productos');
});

test('el kardex de un producto se renderiza', function () {
    [$user, $tenant] = stockTenantUser();
    $product = Product::factory()->for($tenant)->resale()->create(['name' => 'ProductoKardex', 'cost_per_unit' => 20]);
    stockService()->registerAdjustment($product, $tenant->defaultLocation(), 4, 'Inicial', $user);

    $this->actingAs($user)
        ->get(route('stock.show', ['product', $product->id]))
        ->assertOk()
        ->assertSee('ProductoKardex');
});

// --- Endpoints de stock por tipo product ---

test('registrar un ajuste de producto por el controller', function () {
    [$user, $tenant] = stockTenantUser();
    $product = Product::factory()->for($tenant)->resale()->create(['cost_per_unit' => 10]);

    $this->actingAs($user)
        ->post(route('stock.adjustments.store', ['product', $product->id]), [
            'quantity' => 7,
            'reason' => 'Ingreso manual',
        ])
        ->assertRedirect();

    expect((float) stockService()->levelFor($product, $tenant->defaultLocation())->quantity)->toBe(7.0);
});

test('setear el mínimo de un producto por el controller', function () {
    [$user, $tenant] = stockTenantUser();
    $product = Product::factory()->for($tenant)->resale()->create();

    $this->actingAs($user)
        ->patch(route('stock.min.update', ['product', $product->id]), ['min_quantity' => 3])
        ->assertRedirect();

    expect((float) stockService()->levelFor($product, $tenant->defaultLocation())->min_quantity)->toBe(3.0);
});

// --- Aislamiento ---

test('aislamiento: no se puede tocar el stock de un producto de otro tenant', function () {
    [$user] = stockTenantUser();
    $other = Tenant::factory()->create();
    $foreign = Product::factory()->for($other)->resale()->create();

    $this->actingAs($user)
        ->post(route('stock.adjustments.store', ['product', $foreign->id]), [
            'quantity' => 5,
            'reason' => 'Hack',
        ])
        ->assertNotFound();
});

<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Tenant;
use Symfony\Component\HttpKernel\Exception\HttpException;

// stockPurchaseOwner(), stockPurchaseFor(), stockLineFor() y lineRecorder() son
// helpers globales (definidos en StockPurchaseIntegrationTest).

// --- Imputación de compra sobre un producto de reventa ---

test('comprar un producto de reventa por unidad imputa costo y genera entrada de stock', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 0]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'quantity_purchased' => 10,
        'purchase_unit' => 'u',
        'unit_price' => 50,
    ]);

    lineRecorder()->apply($line);

    $movement = $product->stockMovements()->first();

    expect($movement->type)->toBe(StockMovementType::Purchase)
        ->and((float) $movement->quantity)->toBe(10.0)
        ->and((float) $movement->unit_cost)->toBe(50.0)
        ->and($movement->reference_type)->toBe('purchase_line')
        ->and($movement->reference_id)->toBe($line->id)
        ->and((float) $product->refresh()->cost_per_unit)->toBe(50.0)
        ->and((float) $product->stockLevels()->first()->quantity)->toBe(10.0);
});

test('comprar un producto de reventa con conversión de unidades compatible', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'gr', 'cost_per_unit' => 0]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'quantity_purchased' => 2,
        'purchase_unit' => 'kg',
        'unit_price' => 1000,
    ]);

    lineRecorder()->apply($line);

    // 2 kg → 2000 gr; $1000/kg → $1/gr
    expect((float) $product->stockLevels()->first()->quantity)->toBe(2000.0)
        ->and((float) $product->refresh()->cost_per_unit)->toBe(1.0);
});

test('applyWithCost sobre un producto usa el costo explícito y deriva el stock', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 0]);
    $purchase = stockPurchaseFor($tenant);
    // Bulto de 6 a $300 → costo explícito $50/u → 6 u de stock.
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'quantity_purchased' => 1,
        'purchase_unit' => 'u',
        'unit_price' => 300,
    ]);

    lineRecorder()->applyWithCost($line, 50);

    expect((float) $product->refresh()->cost_per_unit)->toBe(50.0)
        ->and((float) $product->stockLevels()->first()->quantity)->toBe(6.0);
});

// --- Guards ---

test('no se puede imputar una compra a un producto elaborado', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->manufactured()->create();
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'purchase_unit' => 'u',
    ]);

    expect(fn () => lineRecorder()->apply($line))
        ->toThrow(HttpException::class);
});

// --- Flujo por el controller (matchLine) ---

test('matchear un renglón con un producto de reventa por el controller aplica costo y stock', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 0]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchase_unit' => 'u', 'quantity_purchased' => 4, 'unit_price' => 25]);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => "product:{$product->id}"])
        ->assertRedirect();

    expect((float) $product->refresh()->cost_per_unit)->toBe(25.0)
        ->and((float) $product->stockLevels()->first()->quantity)->toBe(4.0);
});

test('desasociar un renglón de producto revierte la entrada de stock', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u']);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'purchase_unit' => 'u',
        'quantity_purchased' => 4,
        'unit_price' => 25,
    ]);
    lineRecorder()->apply($line);
    expect((float) $product->stockLevels()->first()->quantity)->toBe(4.0);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => ''])
        ->assertRedirect();

    // El ledger conserva original + contramovimiento; el saldo neto vuelve a 0.
    expect((float) $product->stockLevels()->first()->quantity)->toBe(0.0)
        ->and($product->stockMovements()->count())->toBe(2);
});

test('el match muestra el optgroup de productos de reventa', function () {
    [$user, $tenant] = stockPurchaseOwner();
    Product::factory()->for($tenant)->resale()->create(['name' => 'GaseosaMatch']);
    Product::factory()->for($tenant)->manufactured()->create(['name' => 'PanNoComprable']);
    $purchase = stockPurchaseFor($tenant);
    stockLineFor($purchase);

    $html = $this->actingAs($user)->get(route('purchases.match', $purchase))->assertOk()->getContent();

    expect($html)->toContain('Productos (reventa)')
        ->toContain('GaseosaMatch')
        ->not->toContain('PanNoComprable');
});

// --- Aislamiento ---

test('aislamiento: no se puede matchear un producto de otro tenant', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $other = Tenant::factory()->create();
    $foreign = Product::factory()->for($other)->resale()->create();
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchase_unit' => 'u']);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => "product:{$foreign->id}"])
        ->assertStatus(422);
});

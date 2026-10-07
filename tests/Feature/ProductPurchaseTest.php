<?php

use App\Enums\StockMovementType;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\SupplierProductLink;
use App\Models\Tenant;
use App\Services\ProductLinkMemory;
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

// --- Pack de reventa (divisor por renglón) ---

test('matchear un pack x6 de reventa por el controller divide el costo, suma 6 u y recuerda el divisor', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 0]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'raw_name' => 'Pack 6 Coca-Cola Zero',
        'purchase_unit' => 'u',
        'quantity_purchased' => 1,
        'unit_price' => 6000,
    ]);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), [
            'match' => "product:{$product->id}",
            'unit_cost' => '1000.0000',
            'pkg_qty' => 6,
        ])
        ->assertRedirect();

    expect((float) $product->refresh()->cost_per_unit)->toBe(1000.0)
        ->and((float) $product->stockLevels()->first()->quantity)->toBe(6.0)
        ->and((float) SupplierProductLink::query()->value('pkg_qty'))->toBe(6.0);
});

test('apply() sobre reventa usa el divisor recordado del proveedor para el mismo artículo', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 0]);
    $purchase = stockPurchaseFor($tenant);
    $first = stockLineFor($purchase, [
        'raw_name' => 'Pack 6 Coca-Cola Zero',
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'purchase_unit' => 'u',
        'quantity_purchased' => 1,
        'unit_price' => 6000,
    ]);
    app(ProductLinkMemory::class)->remember($first, 6.0);

    $next = stockLineFor($tenant->purchases()->create(['supplier_id' => $purchase->supplier_id, 'invoice_date' => '2026-07-08']), [
        'raw_name' => 'Pack 6 Coca-Cola Zero',
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'purchase_unit' => 'u',
        'quantity_purchased' => 2,
        'unit_price' => 6000,
    ]);

    lineRecorder()->apply($next);

    expect((float) $product->refresh()->cost_per_unit)->toBe(1000.0)
        ->and((float) $product->stockLevels()->first()->quantity)->toBe(12.0);
});

test('el divisor recordado de otro artículo se ignora', function () {
    [, $tenant] = stockPurchaseOwner();
    $other = Product::factory()->for($tenant)->resale()->create(['unit' => 'u']);
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 0]);
    $purchase = stockPurchaseFor($tenant);
    $first = stockLineFor($purchase, [
        'raw_name' => 'Pack 6 Coca-Cola Zero',
        'purchaseable_type' => 'product',
        'purchaseable_id' => $other->id,
        'purchase_unit' => 'u',
    ]);
    app(ProductLinkMemory::class)->remember($first, 6.0);

    $next = stockLineFor($tenant->purchases()->create(['supplier_id' => $purchase->supplier_id, 'invoice_date' => '2026-07-08']), [
        'raw_name' => 'Pack 6 Coca-Cola Zero',
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'purchase_unit' => 'u',
        'quantity_purchased' => 1,
        'unit_price' => 6000,
    ]);

    lineRecorder()->apply($next);

    expect((float) $product->refresh()->cost_per_unit)->toBe(6000.0)
        ->and((float) $product->stockLevels()->first()->quantity)->toBe(1.0);
});

test('un pack x6 bonificado entra al stock como 6 u sin tocar el costo', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 800]);
    $line = stockLineFor(stockPurchaseFor($tenant), [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'purchase_unit' => 'u',
        'quantity_purchased' => 1,
        'unit_price' => 0,
        'is_bonus' => true,
    ]);

    lineRecorder()->apply($line, pkgQtyOverride: 6.0);

    expect((float) $product->refresh()->cost_per_unit)->toBe(800.0)
        ->and((float) $product->stockLevels()->first()->quantity)->toBe(6.0);
});

test('mandar pkg_qty=1 pisa un divisor recordado', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 0]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['raw_name' => 'Coca-Cola Zero', 'purchase_unit' => 'u', 'quantity_purchased' => 1, 'unit_price' => 1000]);

    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => "product:{$product->id}", 'unit_cost' => '166.6667', 'pkg_qty' => 6]);
    $this->actingAs($user)
        ->post(route('purchases.lines.match', [$purchase, $line]), ['match' => "product:{$product->id}", 'unit_cost' => '1000.0000', 'pkg_qty' => 1]);

    expect((float) SupplierProductLink::query()->value('pkg_qty'))->toBe(1.0);
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

// --- Aplicar en masa: la reventa no interviene en recetas ---

test('aplicar sugerencias en masa con un renglón de reventa no propaga recetas', function () {
    [$user, $tenant] = stockPurchaseOwner();

    // Descartable y artículo comparten id (tablas distintas, ambas arrancan en 1).
    $packaging = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 50]);
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => 'u', 'cost_per_unit' => 0]);
    expect($product->id)->toBe($packaging->id);

    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 10]);
    $recipe->packagingLines()->create(['packaging_id' => $packaging->id, 'quantity' => 1]);
    // Centinela: si la receta se propaga, el cache se recalcula y pisa este valor.
    $recipe->updateQuietly(['unit_cost' => 999]);

    $purchase = stockPurchaseFor($tenant);
    stockLineFor($purchase, [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'purchase_unit' => 'u',
        'quantity_purchased' => 4,
        'unit_price' => 25,
    ]);

    $this->actingAs($user)
        ->post(route('purchases.apply-suggestions', $purchase))
        ->assertRedirect();

    expect((float) $product->refresh()->cost_per_unit)->toBe(25.0)
        ->and((float) $recipe->refresh()->unit_cost)->toBe(999.0);
});

test('la vista de match muestra el artículo de reventa aplicado, no un descartable ajeno', function () {
    [$user, $tenant] = stockPurchaseOwner();

    $packaging = Packaging::factory()->for($tenant)->create([
        'name' => 'MapleAjeno',
        'cost_per_unit' => 50,
        'subdivisions' => 12,
        'subdivision_label' => 'huevo',
    ]);
    $product = Product::factory()->for($tenant)->resale()->create(['name' => 'GaseosaAplicada', 'unit' => 'u']);
    expect($product->id)->toBe($packaging->id);

    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'product',
        'purchaseable_id' => $product->id,
        'purchase_unit' => 'u',
        'quantity_purchased' => 4,
        'unit_price' => 25,
    ]);
    lineRecorder()->apply($line);

    $html = $this->actingAs($user)->get(route('purchases.match', $purchase))->assertOk()->getContent();

    expect($html)->toContain('GaseosaAplicada')
        ->not->toContain('12 huevo / envase');
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

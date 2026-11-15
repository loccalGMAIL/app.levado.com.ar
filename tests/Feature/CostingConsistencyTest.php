<?php

use App\Enums\CostingMethod;
use App\Enums\MarginLevel;
use App\Enums\PricingPolicy;
use App\Enums\StockMovementType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Exceptions\IncompatibleUnitsException;
use App\Models\FixedCost;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Notification;
use App\Models\Product;
use App\Models\ProductPriceLog;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Services\CreditNoteLineRecorder;
use App\Services\NotificationService;
use App\Services\ProductPriceWriter;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('la devolución por nota de crédito de un artículo de reventa descuenta su stock', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => Unit::Unidad->value, 'cost_per_unit' => 100]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, [
        'purchaseable_type' => 'product', 'purchaseable_id' => $product->id,
        'quantity_purchased' => 10, 'purchase_unit' => 'u', 'unit_price' => 100, 'subtotal' => 1000,
    ]);
    lineRecorder()->apply($line);

    $note = $tenant->creditNotes()->create(['supplier_id' => $purchase->supplier_id, 'purchase_id' => $purchase->id, 'note_date' => '2026-07-10']);
    app(CreditNoteLineRecorder::class)->storeLine($note, [
        'purchase_line_id' => $line->id, 'quantity' => 4, 'unit' => 'u', 'unit_price' => 100, 'affects_stock' => true,
    ]);

    expect((float) $product->stockLevels()->first()->quantity)->toBe(6.0)
        ->and($product->stockMovements()->where('type', StockMovementType::Return->value)->count())->toBe(1);
});

test('varias notas de crédito no pueden devolver más de lo comprado en un renglón', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo]);
    $purchase = stockPurchaseFor($tenant);
    $line = stockLineFor($purchase, ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]); // 2 kg
    lineRecorder()->apply($line);

    $recorder = app(CreditNoteLineRecorder::class);
    $noteData = ['supplier_id' => $purchase->supplier_id, 'purchase_id' => $purchase->id, 'note_date' => '2026-07-10'];
    $returned = ['purchase_line_id' => $line->id, 'quantity' => 1.5, 'unit' => 'kg', 'unit_price' => 1000, 'affects_stock' => true];

    $recorder->storeLine($tenant->creditNotes()->create($noteData), $returned);

    expect(fn () => $recorder->storeLine($tenant->creditNotes()->create($noteData), $returned))
        ->toThrow(HttpException::class, 'no puede superar la cantidad comprada');

    expect((float) $ingredient->stockLevels()->first()->quantity)->toBe(500.0);
});

test('el dashboard toma el precio del artículo base y no el de un pack de la misma receta', function () {
    [$user, $tenant] = productionSetup();
    $recipe = Recipe::where('tenant_id', $tenant->id)->firstOrFail();
    $base = Product::where('recipe_id', $recipe->id)->firstOrFail();
    $priceList = $tenant->defaultPriceList();

    // El pack se crea con id menor que el precio del base para que un "limit 1" sin filtro lo elija.
    $pack = Product::factory()->for($tenant)->presentationOf($recipe, 6)->create();
    app(ProductPriceWriter::class)->set($pack, $priceList, 600.0);
    app(ProductPriceWriter::class)->set($base, $priceList, 100.0);
    propagateRecipeCosts($recipe);

    $rows = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('recipeRows');

    expect($rows->getCollection()->first()['selling_price'])->toBe(100.0);
});

test('cargar los gastos fijos del mes en curso recalcula los precios con política', function () {
    [$user, $tenant, $product] = productionSetup();
    $tenant->update(['productive_hours_month' => 100]);
    $recipe = $product->recipe;
    $laborType = $tenant->laborTypes()->create(['name' => 'Panadero', 'hourly_rate' => 0, 'active' => true]);
    $recipe->laborLines()->create(['labor_type_id' => $laborType->id, 'hours' => 12]);
    propagateRecipeCosts($recipe);

    $category = $tenant->fixedCostCategories()->create(['name' => 'General']);
    $fixedCost = FixedCost::factory()->for($tenant)->create(['fixed_cost_category_id' => $category->id, 'monthly_amount' => 0, 'active' => true]);

    $priceList = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($product->fresh(), $priceList, PricingPolicy::Markup, 100.0);
    $before = $product->currentPrice($priceList);

    $this->actingAs($user)->post(route('fixed-costs.history.store'), [
        'period' => now()->format('Y-m'),
        'amounts' => [$fixedCost->id => '10000.00'],
    ])->assertRedirect();

    // overhead = 10000 / 100 h = $100/h → 12 h / 12 u = +$100 por unidad → recargo 100% = +$200.
    expect($product->currentPrice($priceList))->toBe(round($before + 200, 2));
});

test('el recálculo automático de un precio con política queda en el historial de precios', function () {
    [, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => Unit::Unidad->value, 'cost_per_unit' => 100]);
    $priceList = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($product, $priceList, PricingPolicy::Markup, 50.0);

    buyResale($product, $tenant, 5, 200);

    expect($product->currentPrice($priceList))->toBe(300.0)
        ->and(ProductPriceLog::where('product_id', $product->id)->pluck('price')->map(fn ($p) => (float) $p)->all())
        ->toBe([150.0, 300.0]);
});

test('reimputar un renglón no promedia dos veces la misma compra (promedio ponderado)', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $tenant->setSetting('resale.costing_method', CostingMethod::WeightedAverage->value);
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => Unit::Unidad->value, 'cost_per_unit' => 100]);
    seedStock($product, 10, $user);

    $line = stockLineFor(stockPurchaseFor($tenant), [
        'purchaseable_type' => 'product', 'purchaseable_id' => $product->id,
        'quantity_purchased' => 10, 'purchase_unit' => 'u', 'unit_price' => 200, 'subtotal' => 2000,
    ]);
    lineRecorder()->apply($line);
    expect((float) $product->fresh()->cost_per_unit)->toBe(150.0);

    // Reabrir la factura y guardar el renglón sin cambios no debe mover el costo…
    lineRecorder()->recompute($line->fresh(), ['quantity_purchased' => 10, 'purchase_unit' => 'u', 'unit_price' => 200]);
    expect((float) $product->fresh()->cost_per_unit)->toBe(150.0);

    // …y corregir el precio recalcula contra el stock previo a la compra, no contra el ya promediado.
    lineRecorder()->recompute($line->fresh(), ['quantity_purchased' => 10, 'purchase_unit' => 'u', 'unit_price' => 300]);
    expect((float) $product->fresh()->cost_per_unit)->toBe(200.0)
        ->and((float) $product->stockLevels()->first()->quantity)->toBe(20.0);
});

test('reimputar un renglón sin cambios no duplica el historial de costo del insumo', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo, 'cost_per_unit' => 0.5]);
    $line = stockLineFor(stockPurchaseFor($tenant), ['purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id]);

    lineRecorder()->apply($line);
    $logs = $ingredient->priceLogs()->count();

    lineRecorder()->recompute($line->fresh(), ['quantity_purchased' => 2, 'purchase_unit' => 'kg', 'unit_price' => 1000]);
    expect($ingredient->priceLogs()->count())->toBe($logs);

    lineRecorder()->recompute($line->fresh(), ['quantity_purchased' => 2, 'purchase_unit' => 'kg', 'unit_price' => 1200]);
    expect($ingredient->priceLogs()->count())->toBe($logs + 1);
});

test('una palabra que empieza con g o l no se lee como divisor de gramos o litros', function () {
    [, $tenant] = stockPurchaseOwner();
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Kilogramo, 'cost_per_unit' => 10]);
    $line = stockLineFor(stockPurchaseFor($tenant), [
        'raw_name' => 'GALLETITAS X 3 GUSTOS', 'purchaseable_type' => 'ingredient', 'purchaseable_id' => $ingredient->id,
        'quantity_purchased' => 1, 'purchase_unit' => 'u', 'unit_price' => 900, 'subtotal' => 900,
    ]);

    expect(fn () => lineRecorder()->apply($line))->toThrow(IncompatibleUnitsException::class);
    expect((float) $ingredient->fresh()->cost_per_unit)->toBe(10.0);
});

test('no se puede cambiar la unidad de un insumo que ya está en uso', function () {
    [$user, $tenant, , $harina] = productionSetup();

    $this->actingAs($user)->put(route('ingredients.update', $harina), [
        'name' => $harina->name, 'unit' => Unit::Kilogramo->value, 'cost_per_unit' => 0.01,
    ])->assertSessionHasErrors('unit');

    expect($harina->fresh()->unit)->toBe(Unit::Gramo);

    $libre = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value]);
    $this->actingAs($user)->put(route('ingredients.update', $libre), [
        'name' => $libre->name, 'unit' => Unit::Kilogramo->value, 'cost_per_unit' => 5,
    ])->assertSessionHasNoErrors();

    expect($libre->fresh()->unit)->toBe(Unit::Kilogramo);
});

test('una receta no admite un segundo artículo base', function () {
    [$user, $tenant, $product] = productionSetup();

    $payload = ['name' => 'Otro base', 'type' => 'manufactured', 'recipe_id' => $product->recipe_id, 'unit' => 'u'];

    $this->actingAs($user)->post(route('products.store'), $payload)->assertSessionHasErrors('recipe_id');
    $this->actingAs($user)->post(route('products.store'), $payload + ['recipe_quantity' => 6])->assertSessionHasNoErrors();

    expect($tenant->products()->where('recipe_id', $product->recipe_id)->count())->toBe(2);
});

test('la orden produce en su propia sucursal, la misma que muestra el preview', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    $other = Location::factory()->for($tenant)->create();
    $order = orderWithLine($product, 12, $tenant);
    $order->update(['location_id' => $other->id]);

    productionOrderService()->produce($order->fresh(), $user);

    expect(StockMovement::where('tenant_id', $tenant->id)->where('type', StockMovementType::Production->value)->pluck('location_id')->unique()->all())
        ->toBe([$other->id]);
});

test('producir dos veces la misma orden no duplica la producción', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = orderWithLine($product, 12, $tenant);
    $stale = $order->fresh();

    productionOrderService()->produce($order, $user);

    expect(fn () => productionOrderService()->produce($stale, $user))->toThrow(HttpException::class);
    expect($tenant->productions()->count())->toBe(1);
});

test('un artículo por debajo de su mínimo genera alerta de stock bajo', function () {
    [$user, $tenant] = stockPurchaseOwner();
    $product = Product::factory()->for($tenant)->resale()->create(['unit' => Unit::Unidad->value, 'cost_per_unit' => 100]);
    seedStock($product, 2, $user);
    stockService()->setMinQuantity($product, $tenant->defaultLocation(), 5);

    app(NotificationService::class)->syncStateAlerts($tenant);

    expect(Notification::where('tenant_id', $tenant->id)->where('dedupe_key', "low_stock:product:{$product->id}")->exists())->toBeTrue();
});

test('el margen se clasifica con una única escala', function (?float $pct, ?MarginLevel $level) {
    expect(MarginLevel::for($pct))->toBe($level);
})->with([
    [null, null],
    [75.0, MarginLevel::High],
    [60.0, MarginLevel::High],
    [59.9, MarginLevel::Medium],
    [40.0, MarginLevel::Medium],
    [20.0, MarginLevel::Fair],
    [19.9, MarginLevel::Low],
    [-5.0, MarginLevel::Low],
]);

test('un margen de 100% o más se rechaza en vez de dejar el precio vacío', function () {
    [$user, $tenant, $product] = productionSetup(TenantUserRole::Owner);

    $this->actingAs($user)
        ->patchJson(route('products.prices.update', [$product, $tenant->defaultPriceList()]), ['policy_type' => 'margin', 'policy_value' => 100])
        ->assertStatus(422);
});

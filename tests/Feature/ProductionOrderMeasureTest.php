<?php

use App\Enums\LineMeasure;
use App\Enums\Unit;
use App\Models\Production;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\RecurringProductionRequestLine;
use App\Services\ProductionOrderSheets;
use App\Services\RecurringProductionRequestMaterializer;

// productionSetup() es global (ProductionControllerTest): receta que rinde 12 u
// y consume 500 gr de harina por tanda. Acá además 1 lata = 6 u. seedStock() es
// global (ProductionTest).

function productWithLata(): array
{
    [$user, $tenant, $product, $harina] = productionSetup();
    $product->recipe->update(['production_unit_name' => 'lata', 'production_unit_quantity' => 6]);

    return [$user, $tenant, $product->fresh('recipe'), $harina];
}

function requestFor($tenant): array
{
    $order = ProductionOrder::factory()->for($tenant)->confirmed()->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);

    return [$order, $request];
}

test('sincronizar guarda la medida de cada renglón y la devuelve', function () {
    [$user, $tenant, $product] = productWithLata();
    [$order, $request] = requestFor($tenant);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['product_id' => $product->id, 'quantity' => 0.5, 'measure' => 'production_unit']],
        ])
        ->assertOk()
        ->assertJsonPath('lines.0.measure', 'production_unit')
        ->assertJsonPath('aggregated.0.quantity', 3);

    expect($request->lines()->first()->measure)->toBe(LineMeasure::ProductionUnit);
});

test('sin medida el renglón queda en unidades', function () {
    [$user, $tenant, $product] = productWithLata();
    [$order, $request] = requestFor($tenant);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['product_id' => $product->id, 'quantity' => 4]],
        ])
        ->assertOk();

    expect($request->lines()->first()->measure)->toBe(LineMeasure::Unit);
});

test('una medida inválida se rechaza', function () {
    [$user, $tenant, $product] = productWithLata();
    [$order, $request] = requestFor($tenant);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['product_id' => $product->id, 'quantity' => 1, 'measure' => 'docena']],
        ])
        ->assertJsonValidationErrors('lines.0.measure');
});

test('aggregate suma latas y unidades del mismo artículo en unidades base', function () {
    [$user, $tenant, $product] = productWithLata();
    [$order, $request] = requestFor($tenant);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 0.5, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 10, 'unit' => 'u', 'measure' => 'unit', 'position' => 2]);

    $entry = productionOrderService()->aggregate($order)->first();

    expect($entry['quantity'])->toBe(13.0)
        ->and($entry['batches'])->toEqualWithDelta(13 / 6, 0.0001)
        ->and($entry['batch_label'])->toBe('lata');
});

test('producir una orden con latas fabrica una sola producción en unidades base', function () {
    [$user, $tenant, $product, $harina] = productWithLata();
    seedStock($harina, 5000, $user);
    [$order, $request] = requestFor($tenant);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 2, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);

    productionOrderService()->produce($order, $user);

    $productions = Production::where('production_order_id', $order->id)->get();
    expect($productions)->toHaveCount(1)
        ->and((float) $productions->first()->quantity)->toBe(12.0)
        // 12 u de una receta que rinde 12 = 1 receta = 500 gr de harina.
        ->and((float) $harina->stockLevels()->first()->quantity)->toBe(4500.0);
});

test('la orden instantánea acepta renglones en latas y conserva la medida pedida', function () {
    [$user, $tenant, $product, $harina] = productWithLata();
    seedStock($harina, 5000, $user);

    $this->actingAs($user)
        ->post(route('production-orders.instant.store'), [
            'destination_type' => 'location',
            'destination_id' => $tenant->defaultLocation()->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1.5, 'measure' => 'production_unit'],
                ['product_id' => $product->id, 'quantity' => 3, 'measure' => 'unit'],
            ],
        ])
        ->assertRedirect();

    $order = ProductionOrder::where('tenant_id', $tenant->id)->first();
    $lines = $order->productionOrderRequests()->first()->lines()->orderBy('id')->get();

    expect((float) Production::where('production_order_id', $order->id)->first()->quantity)->toBe(12.0)
        ->and($lines->pluck('measure')->all())->toBe([LineMeasure::ProductionUnit, LineMeasure::Unit])
        ->and($lines->pluck('quantity')->map(fn ($q) => (float) $q)->all())->toBe([1.5, 3.0]);
});

test('el catálogo de la grilla ofrece la unidad de producción de cada receta', function () {
    [$user, $tenant, $product] = productWithLata();

    $entry = productionOrderService()->producibleCatalog($tenant)->firstWhere('id', $product->id);

    expect($entry['production_unit'])->toBe(['label' => 'lata', 'size' => 6.0]);

    $product->recipe->update(['production_unit_name' => null, 'production_unit_quantity' => null]);
    $implicit = productionOrderService()->producibleCatalog($tenant)->firstWhere('id', $product->id);

    expect($implicit['production_unit'])->toBe(['label' => 'Lata', 'size' => 12.0]);
});

test('traer del pedido anterior conserva la medida', function () {
    [$user, $tenant, $product] = productWithLata();
    [$order, $request] = requestFor($tenant);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 2, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);

    $payload = productionOrderService()->previousLinesPayload($tenant, $request->load('lines.product', 'productionOrder'));

    expect($payload['lines'][0]['measure'])->toBe('production_unit');
});

test('el pedido recurrente conserva la medida al generar cada instancia', function () {
    [$user, $tenant, $product] = productWithLata();
    $recurring = recurringWith($tenant, $product, ['weekdays' => [1, 2, 3, 4, 5, 6, 7]]);
    RecurringProductionRequestLine::where('recurring_production_request_id', $recurring->id)
        ->update(['measure' => 'production_unit', 'quantity' => 2]);

    app(RecurringProductionRequestMaterializer::class)->materialize($tenant);

    $line = ProductionOrderRequest::where('recurring_production_request_id', $recurring->id)->first()->lines()->first();
    expect($line->measure)->toBe(LineMeasure::ProductionUnit)
        ->and((float) $line->quantity)->toBe(2.0);
});

test('la planilla de reparto muestra la línea como se pidió con su equivalente', function () {
    [$user, $tenant, $product] = productWithLata();
    [$order, $request] = requestFor($tenant);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 1.5, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);

    $sheet = app(ProductionOrderSheets::class)->delivery($order);
    $line = $sheet['groups'][0]['requests'][0]['lines'][0];

    expect($line['unit'])->toBe('lata')
        ->and($line['quantity'])->toBe(1.5)
        ->and($line['detail'])->toBe('9 u');
});

test('la planilla de producción informa las tandas', function () {
    [$user, $tenant, $product, $harina] = productWithLata();
    [$order, $request] = requestFor($tenant);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 1.5, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);

    $sheet = app(ProductionOrderSheets::class)->production($order);

    expect($sheet['products'][0]['quantity'])->toBe(9.0)
        ->and($sheet['products'][0]['batches'])->toBe('1,5 lata');
});

test('quantityLabel describe la línea en lata y en unidades', function () {
    [$user, $tenant, $product] = productWithLata();
    [$order, $request] = requestFor($tenant);
    $inLata = $request->lines()->create(['product_id' => $product->id, 'quantity' => 1.5, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);
    $inUnits = $request->lines()->create(['product_id' => $product->id, 'quantity' => 4, 'unit' => 'u', 'measure' => 'unit', 'position' => 2]);

    expect($inLata->load('product.recipe')->quantityLabel())->toBe('1,5 lata (9 u)')
        ->and($inUnits->load('product.recipe')->quantityLabel())->toBe('4,00 u');
});

// Redondeo y saltos. productWithLata(): receta de 12 u con 1 lata = 6 u. Acá se
// ajusta la equivalencia para armar los casos de la pastelería.

function setLataSize($product, float $size, string $yieldUnit = 'u', ?string $productUnit = null): void
{
    $product->recipe->update(['yield_quantity' => $size, 'yield_unit' => $yieldUnit, 'production_unit_quantity' => $size]);

    if ($productUnit !== null) {
        $product->update(['unit' => $productUnit]);
    }
}

test('un cuarto de lata de 66 u se produce redondeado hacia arriba y muestra 0,25 latas', function () {
    [$user, $tenant, $product] = productWithLata();
    setLataSize($product, 66);
    [$order, $request] = requestFor($tenant);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 0.25, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);

    $entry = productionOrderService()->aggregate($order)->first();

    expect($entry['quantity'])->toBe(17.0)
        ->and($entry['batches'])->toEqualWithDelta(0.25, 0.0001);
});

test('el redondeo se hace sobre el total: cuatro cuartos suman la lata entera', function () {
    [$user, $tenant, $product] = productWithLata();
    setLataSize($product, 66);
    [$order] = requestFor($tenant);
    foreach (range(1, 4) as $i) {
        $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
        $request->lines()->create(['product_id' => $product->id, 'quantity' => 0.25, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);
    }

    expect(productionOrderService()->aggregate($order)->first()['quantity'])->toBe(66.0);
});

test('un resultado exacto no sube', function () {
    [$user, $tenant, $product] = productWithLata();
    setLataSize($product, 66);
    [$order, $request] = requestFor($tenant);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 0.5, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);

    expect(productionOrderService()->aggregate($order)->first()['quantity'])->toBe(33.0);
});

test('un artículo en kg conserva los decimales', function () {
    [$user, $tenant, $product] = productWithLata();
    setLataSize($product, 3.5, 'kg', 'kg');
    [$order, $request] = requestFor($tenant);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 0.25, 'unit' => 'kg', 'measure' => 'production_unit', 'position' => 1]);

    expect(productionOrderService()->aggregate($order)->first()['quantity'])->toBe(0.875);
});

test('producir fabrica la cantidad redondeada', function () {
    [$user, $tenant, $product, $harina] = productWithLata();
    setLataSize($product, 66);
    seedStock($harina, 50000, $user);
    [$order, $request] = requestFor($tenant);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 0.25, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);

    productionOrderService()->produce($order, $user);

    expect((float) Production::where('production_order_id', $order->id)->first()->quantity)->toBe(17.0);
});

test('la orden instantánea redondea hacia arriba', function () {
    [$user, $tenant, $product, $harina] = productWithLata();
    setLataSize($product, 66);
    seedStock($harina, 50000, $user);

    $this->actingAs($user)
        ->post(route('production-orders.instant.store'), [
            'destination_type' => 'location',
            'destination_id' => $tenant->defaultLocation()->id,
            'items' => [['product_id' => $product->id, 'quantity' => 0.25, 'measure' => 'production_unit']],
        ])
        ->assertRedirect();

    $order = ProductionOrder::where('tenant_id', $tenant->id)->first();
    expect((float) Production::where('production_order_id', $order->id)->first()->quantity)->toBe(17.0);
});

test('roundForProduction sube las unidades y deja los decimales de las demás', function () {
    [$user, $tenant, $product] = productWithLata();

    expect($product->roundForProduction(16.5))->toBe(17.0)
        ->and($product->roundForProduction(16.0000000001))->toBe(16.0)
        ->and($product->roundForProduction(33.0))->toBe(33.0);

    $product->update(['unit' => 'kg']);

    expect($product->roundForProduction(0.8754))->toBe(0.875);
});

test('el equivalente de una línea marca el redondeo con ≈', function () {
    [$user, $tenant, $product] = productWithLata();
    setLataSize($product, 66);
    [$order, $request] = requestFor($tenant);
    $quarter = $request->lines()->create(['product_id' => $product->id, 'quantity' => 0.25, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 1]);
    $half = $request->lines()->create(['product_id' => $product->id, 'quantity' => 0.5, 'unit' => 'u', 'measure' => 'production_unit', 'position' => 2]);

    expect($quarter->load('product.recipe')->equivalentLabel())->toBe('≈ 17 u')
        ->and($quarter->quantityLabel())->toBe('0,25 lata (≈ 17 u)')
        ->and($half->load('product.recipe')->equivalentLabel())->toBe('33 u');

    $sheet = app(ProductionOrderSheets::class)->delivery($order);
    expect(collect($sheet['groups'][0]['requests'][0]['lines'])->pluck('detail')->all())->toBe(['≈ 17 u', '33 u']);
});

test('el catálogo trae los saltos de cada medida según la unidad del artículo', function () {
    [$user, $tenant, $product] = productWithLata();

    expect(productionOrderService()->producibleCatalog($tenant)->firstWhere('id', $product->id)['step'])
        ->toBe(['unit' => 1.0, 'production_unit' => 0.25]);

    $product->update(['unit' => 'kg']);
    expect(productionOrderService()->producibleCatalog($tenant)->firstWhere('id', $product->id)['step'])
        ->toBe(['unit' => 0.5, 'production_unit' => 0.25]);
});

test('LineMeasure::step cubre todas las unidades', function () {
    expect(LineMeasure::Unit->step(Unit::Unidad))->toBe(1.0)
        ->and(LineMeasure::Unit->step(Unit::Kilogramo))->toBe(0.5)
        ->and(LineMeasure::Unit->step(Unit::Litro))->toBe(0.5)
        ->and(LineMeasure::Unit->step(Unit::Gramo))->toBe(50.0)
        ->and(LineMeasure::Unit->step(Unit::Mililitro))->toBe(50.0)
        ->and(LineMeasure::Unit->step(Unit::Centimetro3))->toBe(50.0)
        ->and(LineMeasure::ProductionUnit->step(Unit::Kilogramo))->toBe(0.25);
});

test('la grilla muestra los botones de salto y el modal de pedido los saltos del catálogo', function () {
    [$user, $tenant, $product] = productWithLata();

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertSee('stepQuantity(line, 1)', false)
        ->assertViewHas('products', fn ($products) => $products->firstWhere('id', $product->id)['step'] === ['unit' => 1.0, 'production_unit' => 0.25]);
});

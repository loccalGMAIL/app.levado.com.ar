<?php

use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;

// productionSetup() es global (ProductionControllerTest) — arma un elaborado
// en categoría producible.

test('sincronizar guarda tres renglones nuevos de una', function () {
    [$user, $tenant, $productA] = productionSetup();
    $productB = Product::factory()->for($tenant)->manufactured()->create(['product_category_id' => $productA->product_category_id]);
    $productC = Product::factory()->for($tenant)->manufactured()->create(['product_category_id' => $productA->product_category_id]);
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [
                ['product_id' => $productA->id, 'quantity' => 5],
                ['product_id' => $productB->id, 'quantity' => 1],
                ['product_id' => $productC->id, 'quantity' => 2],
            ],
        ])
        ->assertOk();

    expect($request->lines()->count())->toBe(3);
});

test('sincronizar edita la cantidad de un renglón existente por id', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    $line = $request->lines()->create(['product_id' => $product->id, 'quantity' => 3, 'unit' => $product->unit->value, 'position' => 1]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 9]],
        ])
        ->assertOk();

    expect($request->lines()->count())->toBe(1)
        ->and($line->fresh()->quantity)->toEqualWithDelta(9, 0.001)
        ->and($line->fresh()->id)->toBe($line->id);
});

test('omitir un renglón existente lo borra', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    $keep = $request->lines()->create(['product_id' => $product->id, 'quantity' => 3, 'unit' => $product->unit->value, 'position' => 1]);
    $drop = $request->lines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => $product->unit->value, 'position' => 2]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['id' => $keep->id, 'product_id' => $product->id, 'quantity' => 3]],
        ])
        ->assertOk();

    expect(ProductionOrderLine::find($keep->id))->not->toBeNull()
        ->and(ProductionOrderLine::find($drop->id))->toBeNull();
});

test('lines vacío deja el pedido sin líneas', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 3, 'unit' => $product->unit->value, 'position' => 1]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), ['lines' => []])
        ->assertOk();

    expect($request->lines()->count())->toBe(0);
});

test('un id de línea de otro pedido se trata como alta y no roba la ajena', function () {
    [$user, $tenant, $product] = productionSetup();
    $orderA = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $requestA = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $orderA->id]);
    $orderB = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $requestB = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $orderB->id]);
    $foreignLine = $requestB->lines()->create(['product_id' => $product->id, 'quantity' => 7, 'unit' => $product->unit->value, 'position' => 1]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$orderA, $requestA]), [
            'lines' => [['id' => $foreignLine->id, 'product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertOk();

    expect($requestA->lines()->count())->toBe(1)
        ->and($foreignLine->fresh()->quantity)->toEqualWithDelta(7, 0.001)
        ->and($foreignLine->fresh()->production_order_request_id)->toBe($requestB->id);
});

test('un artículo no producible se rechaza', function () {
    [$user, $tenant] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    $notProducible = Product::factory()->for($tenant)->resale()->create();

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['product_id' => $notProducible->id, 'quantity' => 1]],
        ])
        ->assertStatus(422);
});

test('una cantidad cero o negativa se rechaza', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['product_id' => $product->id, 'quantity' => 0]],
        ])
        ->assertStatus(422);
});

test('una orden terminada rechaza el sync', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->done()->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertStatus(422);
});

test('aislamiento: una orden de otro tenant devuelve 404', function () {
    [$user] = productionSetup();
    $otherTenant = Tenant::factory()->create();
    $otherOrder = ProductionOrder::factory()->for($otherTenant)->create(['location_id' => $otherTenant->defaultLocation()->id]);
    $otherRequest = ProductionOrderRequest::factory()->for($otherTenant)->create(['production_order_id' => $otherOrder->id]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$otherOrder, $otherRequest]), ['lines' => []])
        ->assertNotFound();
});

test('la respuesta trae lines, aggregated y preview coherentes', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);

    $response = $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [['product_id' => $product->id, 'quantity' => 4]],
        ])
        ->assertOk();

    $response->assertJsonPath('lines.0.product_id', $product->id)
        ->assertJsonPath('lines.0.quantity', 4)
        ->assertJsonPath('aggregated.0.product_id', $product->id)
        ->assertJsonPath('aggregated.0.quantity', 4)
        ->assertJsonStructure(['preview' => ['lines', 'material_cost', 'labor_cost', 'total_cost']]);
});

test('position queda 1..N en el orden mandado', function () {
    [$user, $tenant, $productA] = productionSetup();
    $productB = Product::factory()->for($tenant)->manufactured()->create(['product_category_id' => $productA->product_category_id]);
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);

    $this->actingAs($user)
        ->putJson(route('production-orders.requests.lines.sync', [$order, $request]), [
            'lines' => [
                ['product_id' => $productB->id, 'quantity' => 1],
                ['product_id' => $productA->id, 'quantity' => 2],
            ],
        ])
        ->assertOk();

    $lines = $request->lines()->orderBy('position')->get();
    expect($lines->pluck('position')->all())->toBe([1, 2])
        ->and($lines->first()->product_id)->toBe($productB->id)
        ->and($lines->last()->product_id)->toBe($productA->id);
});

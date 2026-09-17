<?php

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Services\ProductionOrderService;

// productionSetup() es global (ProductionControllerTest).

function previousLinesService(): ProductionOrderService
{
    return app(ProductionOrderService::class);
}

test('trae el último pedido al mismo destino con sus cantidades', function () {
    [$user, $tenant, $product] = productionSetup();
    $location = $tenant->defaultLocation();

    $older = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id, 'scheduled_for' => now()->subDays(2)]);
    $olderRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $older->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);
    $olderRequest->lines()->create(['product_id' => $product->id, 'quantity' => 4, 'unit' => $product->unit->value]);

    $newer = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id, 'scheduled_for' => now()->subDay()]);
    $newerRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $newer->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);
    $newerRequest->lines()->create(['product_id' => $product->id, 'quantity' => 9, 'unit' => $product->unit->value]);

    $current = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id]);
    $currentRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $current->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);

    $found = previousLinesService()->previousRequestFor($currentRequest);

    expect($found?->id)->toBe($newerRequest->id)
        ->and($found->lines->first()->quantity)->toEqualWithDelta(9, 0.001);
});

test('ignora las órdenes anuladas', function () {
    [$user, $tenant, $product] = productionSetup();
    $location = $tenant->defaultLocation();

    $cancelled = ProductionOrder::factory()->for($tenant)->cancelled()->create(['location_id' => $location->id]);
    $cancelledRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $cancelled->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);
    $cancelledRequest->lines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => $product->unit->value]);

    $current = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id]);
    $currentRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $current->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);

    expect(previousLinesService()->previousRequestFor($currentRequest))->toBeNull();
});

test('ignora las plantillas', function () {
    [$user, $tenant, $product] = productionSetup();
    $location = $tenant->defaultLocation();

    // template() crea la orden con is_template=true — ProductionOrderRequest
    // no tiene el global scope (sólo ProductionOrder lo tiene), así que un
    // create() directo alcanza.
    $template = ProductionOrder::factory()->for($tenant)->template()->create(['location_id' => $location->id]);
    $templateRequest = ProductionOrderRequest::create([
        'tenant_id' => $tenant->id,
        'production_order_id' => $template->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
        'position' => 1,
    ]);
    $templateRequest->lines()->create(['product_id' => $product->id, 'quantity' => 1, 'unit' => $product->unit->value]);

    $current = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id]);
    $currentRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $current->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);

    expect(previousLinesService()->previousRequestFor($currentRequest))->toBeNull();
});

test('ignora los pedidos de la orden actual', function () {
    [$user, $tenant, $product] = productionSetup();
    $location = $tenant->defaultLocation();

    $current = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id]);
    $siblingRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $current->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);
    $siblingRequest->lines()->create(['product_id' => $product->id, 'quantity' => 3, 'unit' => $product->unit->value]);
    $currentRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $current->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);

    expect(previousLinesService()->previousRequestFor($currentRequest))->toBeNull();
});

test('un artículo que dejó de ser producible sale en skipped, no en lines', function () {
    [$user, $tenant, $product] = productionSetup();
    $location = $tenant->defaultLocation();
    // Con el gate invertido, "sin categoría" es producible por default: hace
    // falta una categoría marcada explícitamente "no se produce" para que
    // deje de aparecer.
    $noProducibleCategory = $tenant->productCategories()->create(['name' => 'Cafetería', 'producible' => false]);
    $noLongerProducible = Product::factory()->for($tenant)->manufactured()->create(['product_category_id' => $noProducibleCategory->id]);

    $older = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id, 'scheduled_for' => now()->subDay()]);
    $olderRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $older->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);
    $olderRequest->lines()->create(['product_id' => $product->id, 'quantity' => 2, 'unit' => $product->unit->value]);
    $olderRequest->lines()->create(['product_id' => $noLongerProducible->id, 'quantity' => 1, 'unit' => $noLongerProducible->unit->value]);

    $current = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id]);
    $currentRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $current->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('production-orders.requests.previous-lines', [$current, $currentRequest]))
        ->assertOk();

    $response->assertJsonPath('found', true)
        ->assertJsonCount(1, 'lines')
        ->assertJsonPath('lines.0.product_id', $product->id)
        ->assertJsonPath('skipped.0', $noLongerProducible->name);
});

test('sin historial devuelve found false', function () {
    [$user, $tenant] = productionSetup();
    $location = $tenant->defaultLocation();

    $current = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id]);
    $currentRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $current->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('production-orders.requests.previous-lines', [$current, $currentRequest]))
        ->assertOk()
        ->assertJsonPath('found', false);
});

test('no persiste nada', function () {
    [$user, $tenant, $product] = productionSetup();
    $location = $tenant->defaultLocation();

    $older = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id, 'scheduled_for' => now()->subDay()]);
    $olderRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $older->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);
    $olderRequest->lines()->create(['product_id' => $product->id, 'quantity' => 2, 'unit' => $product->unit->value]);

    $current = ProductionOrder::factory()->for($tenant)->create(['location_id' => $location->id]);
    $currentRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $current->id,
        'destination_type' => 'location',
        'destination_id' => $location->id,
    ]);

    $countBefore = $currentRequest->lines()->count();

    $this->actingAs($user)
        ->getJson(route('production-orders.requests.previous-lines', [$current, $currentRequest]))
        ->assertOk();

    expect($currentRequest->lines()->count())->toBe($countBefore);
});

test('un destino distinto no lo trae', function () {
    [$user, $tenant, $product] = productionSetup();
    $locationA = $tenant->defaultLocation();
    $locationB = Location::factory()->for($tenant)->create();

    $older = ProductionOrder::factory()->for($tenant)->create(['location_id' => $locationA->id, 'scheduled_for' => now()->subDay()]);
    $olderRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $older->id,
        'destination_type' => 'location',
        'destination_id' => $locationA->id,
    ]);
    $olderRequest->lines()->create(['product_id' => $product->id, 'quantity' => 2, 'unit' => $product->unit->value]);

    $current = ProductionOrder::factory()->for($tenant)->create(['location_id' => $locationB->id]);
    $currentRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $current->id,
        'destination_type' => 'location',
        'destination_id' => $locationB->id,
    ]);

    expect(previousLinesService()->previousRequestFor($currentRequest))->toBeNull();
});

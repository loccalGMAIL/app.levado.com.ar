<?php

use App\Enums\DeliveryDestinationType;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionOrderType;
use App\Enums\TenantUserRole;
use App\Models\DeliveryPerson;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\RecurringProductionRequest;
use App\Models\Tenant;
use App\Services\ProductionOrderService;

// productionSetup() es global (ProductionControllerTest) — arma un elaborado
// en categoría producible. tenantUserAs() es global (IngredientCrudTest).

function placeRequestPayload(Tenant $tenant, ?Product $product = null, array $overrides = []): array
{
    return [
        'destination_type' => 'location',
        'destination_id' => $tenant->defaultLocation()->id,
        'scheduled_for' => now()->toDateString(),
        'lines' => $product ? [['product_id' => $product->id, 'quantity' => 3]] : [],
        ...$overrides,
    ];
}

test('cargar un pedido crea la orden diaria del día si no existe', function () {
    [$user, $tenant, $product] = productionSetup();

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product))
        ->assertRedirect();

    $order = ProductionOrder::where('tenant_id', $tenant->id)->first();
    expect($order)->not->toBeNull()
        ->and($order->type)->toBe(ProductionOrderType::Daily)
        ->and($order->status)->toBe(ProductionOrderStatus::Draft)
        ->and($order->number)->toBe(1)
        ->and($order->productionOrderRequests()->count())->toBe(1);
});

test('un segundo pedido para la misma fecha se cuelga de la orden Draft ya creada', function () {
    [$user, $tenant, $product] = productionSetup();
    $deliveryPerson = DeliveryPerson::factory()->for($tenant)->create();

    $this->actingAs($user)->post(route('production-requests.store'), placeRequestPayload($tenant, $product))->assertRedirect();
    $this->actingAs($user)->post(route('production-requests.store'), placeRequestPayload($tenant, $product, [
        'destination_type' => 'delivery_person',
        'destination_id' => $deliveryPerson->id,
    ]))->assertRedirect();

    expect(ProductionOrder::where('tenant_id', $tenant->id)->count())->toBe(1);
    $order = ProductionOrder::where('tenant_id', $tenant->id)->first();
    $requests = $order->productionOrderRequests()->orderBy('id')->get();
    expect($requests)->toHaveCount(2)
        ->and($requests->pluck('position')->all())->toBe([1, 2])
        ->and($requests->pluck('number')->all())->toBe([1, 2]);
});

test('se cuelga igual de una orden Confirmed', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->confirmed()->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product))
        ->assertRedirect(route('production-orders.show', $order));

    expect($order->productionOrderRequests()->count())->toBe(1);
});

test('se cuelga igual de una orden InProduction', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->confirmed()->create([
        'location_id' => $tenant->defaultLocation()->id,
        'status' => 'in_production',
    ]);

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product))
        ->assertRedirect(route('production-orders.show', $order));

    expect($order->productionOrderRequests()->count())->toBe(1);
});

test('una orden Done no se reusa: se abre una orden nueva para la misma fecha', function () {
    [$user, $tenant, $product] = productionSetup();
    $done = ProductionOrder::factory()->for($tenant)->done()->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product))
        ->assertRedirect();

    $newOrder = ProductionOrder::where('tenant_id', $tenant->id)->where('id', '!=', $done->id)->first();
    expect($newOrder)->not->toBeNull()
        ->and($newOrder->status)->toBe(ProductionOrderStatus::Draft)
        ->and($done->productionOrderRequests()->count())->toBe(0);
});

test('una orden Cancelled no se reusa: se abre una orden nueva para la misma fecha', function () {
    [$user, $tenant, $product] = productionSetup();
    $cancelled = ProductionOrder::factory()->for($tenant)->cancelled()->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product))
        ->assertRedirect();

    expect(ProductionOrder::where('tenant_id', $tenant->id)->count())->toBe(2)
        ->and($cancelled->productionOrderRequests()->count())->toBe(0);
});

test('no se cuelga de una orden espontánea', function () {
    [$user, $tenant, $product] = productionSetup();
    $spontaneous = ProductionOrder::factory()->for($tenant)->spontaneous()->create(['location_id' => $tenant->defaultLocation()->id]);

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product))
        ->assertRedirect();

    expect($spontaneous->productionOrderRequests()->count())->toBe(0)
        ->and(ProductionOrder::where('tenant_id', $tenant->id)->where('type', 'daily')->count())->toBe(1);
});

test('no se cuelga de una orden instantánea', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);
    $instant = ordersService()->produceInstant(
        $tenant, $user, DeliveryDestinationType::Location, $tenant->defaultLocation()->id,
        collect([['product' => $product, 'quantity' => 1]]),
    );

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product))
        ->assertRedirect();

    expect(ProductionOrder::where('tenant_id', $tenant->id)->where('id', '!=', $instant->id)->where('type', 'daily')->count())->toBe(1);
});

test('un artículo no producible se rechaza', function () {
    [$user, $tenant] = productionSetup();
    $notProducible = Product::factory()->for($tenant)->resale()->create();

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, null, [
            'lines' => [['product_id' => $notProducible->id, 'quantity' => 1]],
        ]))
        ->assertSessionHasErrors('lines.0.product_id');
});

test('un destino de otro negocio se rechaza', function () {
    [$user, $tenant, $product] = productionSetup();
    $otherTenant = Tenant::factory()->create();
    $foreignLocation = $otherTenant->defaultLocation();

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product, ['destination_id' => $foreignLocation->id]))
        ->assertSessionHasErrors('destination_id');
});

test('copy_previous trae los artículos del último pedido a ese destino', function () {
    [$user, $tenant, $product] = productionSetup();
    $productB = Product::factory()->for($tenant)->manufactured()->create(['product_category_id' => $product->product_category_id]);
    $olderOrder = ProductionOrder::factory()->for($tenant)->create([
        'location_id' => $tenant->defaultLocation()->id,
        'scheduled_for' => now()->subDay(),
    ]);
    $olderRequest = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $olderOrder->id,
        'destination_type' => 'location',
        'destination_id' => $tenant->defaultLocation()->id,
    ]);
    $olderRequest->lines()->create(['product_id' => $productB->id, 'quantity' => 7, 'unit' => $productB->unit->value]);

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, null, ['copy_previous' => true]))
        ->assertRedirect();

    $newOrder = ProductionOrder::where('tenant_id', $tenant->id)->where('id', '!=', $olderOrder->id)->first();
    $newRequest = $newOrder->productionOrderRequests()->first();
    expect($newRequest->lines()->count())->toBe(1)
        ->and($newRequest->lines()->first()->product_id)->toBe($productB->id)
        ->and((float) $newRequest->lines()->first()->quantity)->toEqualWithDelta(7, 0.001);
});

test('copy_previous no hace nada si no hay pedido anterior a ese destino', function () {
    [$user, $tenant] = productionSetup();

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, null, ['copy_previous' => true]))
        ->assertRedirect();

    $order = ProductionOrder::where('tenant_id', $tenant->id)->first();
    expect($order->productionOrderRequests()->first()->lines()->count())->toBe(0);
});

test('viewer no puede cargar un pedido', function () {
    [$user, $tenant, $product] = productionSetup(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product))
        ->assertForbidden();
});

test('el endpoint de previous-lines por destino no persiste nada', function () {
    [$user, $tenant, $product] = productionSetup();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = app(ProductionOrderService::class)->addRequest($order, [
        'destination_type' => 'location',
        'destination_id' => $tenant->defaultLocation()->id,
    ]);
    $request->lines()->create(['product_id' => $product->id, 'quantity' => 4, 'unit' => $product->unit->value]);

    $response = $this->actingAs($user)
        ->getJson(route('production-requests.previous-lines', [
            'destination_type' => 'location',
            'destination_id' => $tenant->defaultLocation()->id,
        ]))
        ->assertOk();

    $response->assertJsonPath('found', true)
        ->assertJsonPath('lines.0.product_id', $product->id);
    expect(ProductionOrderRequest::where('tenant_id', $tenant->id)->count())->toBe(1); // el que ya existía, nada nuevo
});

// --- Recurrencia: placeRequest() con recurrence crea el molde de una ---

test('cargar un pedido marcado como recurrente crea el molde y vincula la primera instancia', function () {
    [$user, $tenant, $product] = productionSetup();

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product, [
            'recurrence' => ['weekdays' => [1, 2, 3, 4, 5, 6]],
        ]))
        ->assertRedirect();

    $recurring = RecurringProductionRequest::where('tenant_id', $tenant->id)->first();
    $request = ProductionOrderRequest::where('tenant_id', $tenant->id)->first();

    expect($recurring)->not->toBeNull()
        ->and($recurring->weekdays)->toBe([1, 2, 3, 4, 5, 6])
        ->and($recurring->starts_on->toDateString())->toBe(now()->toDateString())
        ->and($recurring->lines()->count())->toBe(1)
        ->and($recurring->lines()->first()->product_id)->toBe($product->id)
        ->and($request->recurring_production_request_id)->toBe($recurring->id);
});

test('un pedido sin recurrence no crea ningún molde', function () {
    [$user, $tenant, $product] = productionSetup();

    $this->actingAs($user)
        ->post(route('production-requests.store'), placeRequestPayload($tenant, $product))
        ->assertRedirect();

    expect(RecurringProductionRequest::where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(ProductionOrderRequest::where('tenant_id', $tenant->id)->first()->recurring_production_request_id)->toBeNull();
});

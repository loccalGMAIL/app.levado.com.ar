<?php

use App\Enums\DeliveryDestinationType;
use App\Enums\ProductionOrderStatus;
use App\Models\Customer;
use App\Models\Location;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\ProductionOrderRequest;
use App\Models\Tenant;

test('ProductionOrderFactory crea una orden diaria en borrador', function () {
    $order = ProductionOrder::factory()->create();

    expect($order->isDraft())->toBeTrue()
        ->and($order->is_template)->toBeFalse()
        ->and($order->scheduled_for)->not->toBeNull();
});

test('el listado normal de órdenes excluye las plantillas', function () {
    $tenant = Tenant::factory()->create();
    $normal = ProductionOrder::factory()->for($tenant)->create();
    ProductionOrder::factory()->for($tenant)->template()->create();

    $ids = ProductionOrder::query()->pluck('id');

    expect($ids)->toContain($normal->id)
        ->and($ids)->toHaveCount(1);
});

test('withTemplates trae todo y onlyTemplates trae sólo las plantillas', function () {
    $tenant = Tenant::factory()->create();
    $normal = ProductionOrder::factory()->for($tenant)->create();
    $template = ProductionOrder::factory()->for($tenant)->template()->create();

    expect(ProductionOrder::withTemplates()->pluck('id'))->toHaveCount(2)
        ->and(ProductionOrder::onlyTemplates()->pluck('id')->all())->toBe([$template->id])
        ->and(ProductionOrder::onlyTemplates()->pluck('id'))->not->toContain($normal->id);
});

test('un pedido con destino sucursal resuelve la relación morph a Location', function () {
    $tenant = Tenant::factory()->create();
    $location = Location::factory()->for($tenant)->create();
    $request = ProductionOrderRequest::factory()->for($tenant)->create([
        'destination_type' => DeliveryDestinationType::Location->value,
        'destination_id' => $location->id,
    ]);

    expect($request->destination)->toBeInstanceOf(Location::class)
        ->and($request->destination->id)->toBe($location->id)
        ->and($request->destination_type)->toBe(DeliveryDestinationType::Location);
});

test('un pedido con destino cliente resuelve la relación morph a Customer', function () {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->for($tenant)->create();
    $request = ProductionOrderRequest::factory()->for($tenant)->create([
        'destination_type' => DeliveryDestinationType::Customer->value,
        'destination_id' => $customer->id,
    ]);

    expect($request->destination)->toBeInstanceOf(Customer::class)
        ->and($request->destination->id)->toBe($customer->id);
});

test('una línea de pedido pertenece a su pedido y a su artículo', function () {
    $line = ProductionOrderLine::factory()->create();

    expect($line->request)->toBeInstanceOf(ProductionOrderRequest::class)
        ->and($line->product)->not->toBeNull()
        ->and($line->product->isManufactured())->toBeTrue();
});

test('canTransitionTo describe el ciclo de estados de la orden', function () {
    expect(ProductionOrderStatus::Draft->canTransitionTo(ProductionOrderStatus::Confirmed))->toBeTrue()
        ->and(ProductionOrderStatus::Draft->canTransitionTo(ProductionOrderStatus::Done))->toBeFalse()
        ->and(ProductionOrderStatus::Confirmed->canTransitionTo(ProductionOrderStatus::Done))->toBeTrue()
        ->and(ProductionOrderStatus::Confirmed->canTransitionTo(ProductionOrderStatus::InProduction))->toBeTrue()
        ->and(ProductionOrderStatus::InProduction->canTransitionTo(ProductionOrderStatus::Done))->toBeTrue()
        ->and(ProductionOrderStatus::Done->canTransitionTo(ProductionOrderStatus::Draft))->toBeFalse()
        ->and(ProductionOrderStatus::Cancelled->canTransitionTo(ProductionOrderStatus::Draft))->toBeFalse();
});

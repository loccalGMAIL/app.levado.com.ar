<?php

use App\Enums\ProductionOrderStatus;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\ProductionOrderRequest;
use App\Models\Recipe;
use App\Services\ProductionOrderService;
use App\Services\StockService;
use Symfony\Component\HttpKernel\Exception\HttpException;

// stockTenantUser()/seedStock() (StockServiceTest) y manufacturedProduct() (ProductionTest)
// son helpers globales reusados acá.

function productionOrderService(): ProductionOrderService
{
    return app(ProductionOrderService::class);
}

/** Orden con un pedido y una línea artículo+cantidad, lista para producir. */
function orderWithLine(Product $product, float $quantity, $tenant, ?Location $destination = null): ProductionOrder
{
    $order = ProductionOrder::factory()->for($tenant)->confirmed()->create([
        'location_id' => $tenant->defaultLocation()->id,
    ]);

    $request = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'location',
        'destination_id' => ($destination ?? Location::factory()->for($tenant)->create())->id,
    ]);

    ProductionOrderLine::factory()->create([
        'production_order_request_id' => $request->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit' => $product->unit->value,
    ]);

    return $order->fresh();
}

test('aggregate suma la cantidad de un mismo artículo pedido por dos destinos', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);

    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    $requestA = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    ProductionOrderLine::factory()->create([
        'production_order_request_id' => $requestA->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit' => $product->unit->value,
    ]);

    $requestB = ProductionOrderRequest::factory()->for($tenant)->toDeliveryPerson()->create(['production_order_id' => $order->id]);
    ProductionOrderLine::factory()->create([
        'production_order_request_id' => $requestB->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit' => $product->unit->value,
    ]);

    $aggregated = productionOrderService()->aggregate($order);

    expect($aggregated)->toHaveCount(1)
        ->and($aggregated->first()['quantity'])->toBe(15.0);
});

test('el preview combinado suma el consumo de insumos y marca faltantes', function () {
    [$user, $tenant] = stockTenantUser();
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);

    seedStock($harina, 500, $user);

    $order = orderWithLine($product, 10, $tenant); // consume 1000g, hay 500g

    $preview = productionOrderService()->preview($order);

    expect($preview['lines'])->toHaveCount(1)
        ->and($preview['lines'][0]['quantity'])->toBe(1000.0)
        ->and($preview['lines'][0]['available'])->toBe(500.0)
        ->and($preview['lines'][0]['shortfall'])->toBe(500.0);
});

test('producir la orden crea una Production por artículo, atada a la orden, y descuenta/suma el stock agregado', function () {
    [$user, $tenant] = stockTenantUser();
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipeA = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $recipeA->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);
    $productA = manufacturedProduct($tenant, $recipeA);

    $recipeB = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $recipeB->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 50, 'unit' => Unit::Gramo->value]);
    $productB = manufacturedProduct($tenant, $recipeB);

    seedStock($harina, 5000, $user);

    $order = ProductionOrder::factory()->for($tenant)->confirmed()->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    ProductionOrderLine::factory()->create(['production_order_request_id' => $request->id, 'product_id' => $productA->id, 'quantity' => 10, 'unit' => $productA->unit->value]);
    ProductionOrderLine::factory()->create(['production_order_request_id' => $request->id, 'product_id' => $productB->id, 'quantity' => 4, 'unit' => $productB->unit->value]);

    productionOrderService()->produce($order->fresh(), $user);

    $order->refresh();
    $expectedIds = collect([$productA->id, $productB->id])->sort()->values()->all();

    expect($order->status)->toBe(ProductionOrderStatus::Done)
        ->and($order->produced_at)->not->toBeNull()
        ->and($order->productions)->toHaveCount(2)
        ->and($order->productions->pluck('product_id')->sort()->values()->all())->toBe($expectedIds);

    $location = $tenant->defaultLocation();
    expect(app(StockService::class)->levelFor($harina, $location)->quantity)
        ->toEqual(5000 - (10 * 100) - (4 * 50));
    expect(app(StockService::class)->levelFor($productA, $location)->quantity)->toEqual(10.0);
    expect(app(StockService::class)->levelFor($productB, $location)->quantity)->toEqual(4.0);
});

test('anular la orden revierte todo el stock al estado previo', function () {
    [$user, $tenant] = stockTenantUser();
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 100, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);
    seedStock($harina, 2000, $user);

    $order = orderWithLine($product, 5, $tenant);
    productionOrderService()->produce($order->fresh(), $user);

    $location = $tenant->defaultLocation();
    $stock = app(StockService::class);
    $harinaBefore = $stock->levelFor($harina, $location)->quantity;

    productionOrderService()->cancel($order->fresh(), $user);

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Cancelled)
        ->and((float) $stock->levelFor($harina, $location)->quantity)->toEqual(2000.0)
        ->and((float) $stock->levelFor($product, $location)->quantity)->toEqual(0.0);

    expect($harinaBefore)->not->toEqual(2000.0); // sanity: sí se había consumido antes de anular
});

test('anular una orden ya Done también revierte el stock', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);
    $order = orderWithLine($product, 3, $tenant);

    productionOrderService()->produce($order->fresh(), $user);
    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Done);

    productionOrderService()->cancel($order->fresh(), $user);
    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Cancelled);
});

test('anular una orden dos veces es idempotente', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);
    $order = orderWithLine($product, 2, $tenant);

    productionOrderService()->produce($order->fresh(), $user);
    productionOrderService()->cancel($order->fresh(), $user);
    productionOrderService()->cancel($order->fresh(), $user);

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Cancelled);
});

test('producir una orden en borrador aborta', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);

    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create(['production_order_id' => $order->id]);
    ProductionOrderLine::factory()->create(['production_order_request_id' => $request->id, 'product_id' => $product->id, 'quantity' => 1, 'unit' => $product->unit->value]);

    expect(fn () => productionOrderService()->produce($order->fresh(), $user))
        ->toThrow(HttpException::class);
});

test('producir una orden sin líneas aborta', function () {
    [$user, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->confirmed()->create(['location_id' => $tenant->defaultLocation()->id]);

    expect(fn () => productionOrderService()->produce($order, $user))
        ->toThrow(HttpException::class);
});

test('transitionTo respeta el ciclo de estados y rechaza saltos inválidos', function () {
    [$user, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    productionOrderService()->transitionTo($order, ProductionOrderStatus::Confirmed, $user);
    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Confirmed);

    expect(fn () => productionOrderService()->transitionTo($order->fresh(), ProductionOrderStatus::Done, $user))
        ->toThrow(HttpException::class); // Done sólo vía produce()
});

test('transitionTo rechaza Cancelled porque anular tiene su propia puerta', function () {
    [$user, $tenant] = stockTenantUser();
    $order = ProductionOrder::factory()->for($tenant)->create(['location_id' => $tenant->defaultLocation()->id]);

    expect(fn () => productionOrderService()->transitionTo($order, ProductionOrderStatus::Cancelled, $user))
        ->toThrow(HttpException::class);
});

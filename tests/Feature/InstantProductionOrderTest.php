<?php

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionOrderType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\DeliveryPerson;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Services\StockService;

// tenantUserAs() es global (IngredientCrudTest); manufacturedProduct() y
// seedStock() son globales (ProductionTest); productionSetup() es global
// (ProductionControllerTest) — arma un elaborado en categoría producible.

test('la pantalla de orden instantánea lista los elaborados producibles', function () {
    [$user, $tenant, $product] = productionSetup();

    $this->actingAs($user)->get(route('production-orders.instant.create'))->assertOk()->assertSee($product->name);
});

test('la pantalla muestra un elaborado sin categoría', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    manufacturedProduct($tenant, $recipe)->update(['name' => 'ElaboradoSinCategoriaZZ']);

    $this->actingAs($user)->get(route('production-orders.instant.create'))->assertOk()->assertSee('ElaboradoSinCategoriaZZ');
});

test('la pantalla oculta un elaborado de una categoría que no se produce', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);
    $cafeteria = $tenant->productCategories()->create(['name' => 'Cafetería', 'producible' => false]);
    $product->update(['name' => 'ElaboradoCafeteriaZZ', 'product_category_id' => $cafeteria->id]);

    $this->actingAs($user)->get(route('production-orders.instant.create'))->assertOk()->assertDontSee('ElaboradoCafeteriaZZ');
});

test('producir una orden instantánea deja la orden terminada', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);
    $location = $tenant->defaultLocation();

    $this->actingAs($user)
        ->post(route('production-orders.instant.store'), [
            'destination_type' => 'location',
            'destination_id' => $location->id,
            'items' => [['product_id' => $product->id, 'quantity' => 24]],
        ])
        ->assertRedirect();

    $order = ProductionOrder::where('tenant_id', $tenant->id)->first();
    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(ProductionOrderStatus::Done)
        ->and($order->type)->toBe(ProductionOrderType::Instant);
});

test('la orden instantánea descuenta insumos y suma el stock del elaborado', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);
    $location = $tenant->defaultLocation();

    $this->actingAs($user)->post(route('production-orders.instant.store'), [
        'destination_type' => 'location',
        'destination_id' => $location->id,
        'items' => [['product_id' => $product->id, 'quantity' => 24]],
    ]);

    $stock = app(StockService::class);
    expect((float) $stock->levelFor($harina->fresh(), $location)->quantity)->toBe(4000.0) // 5000 - 500*2
        ->and((float) $stock->levelFor($product->fresh(), $location)->quantity)->toBe(24.0);
});

test('la orden instantánea queda con tipo Instantánea y un pedido con el destino elegido', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);
    $sucursal = Location::factory()->for($tenant)->create();

    $this->actingAs($user)->post(route('production-orders.instant.store'), [
        'destination_type' => 'location',
        'destination_id' => $sucursal->id,
        'items' => [['product_id' => $product->id, 'quantity' => 12]],
    ]);

    $order = ProductionOrder::where('tenant_id', $tenant->id)->with('productionOrderRequests')->first();
    expect($order->productionOrderRequests)->toHaveCount(1)
        ->and($order->productionOrderRequests->first()->destination_id)->toBe($sucursal->id)
        ->and($order->productionOrderRequests->first()->position)->toBe(1);
});

test('la orden instantánea admite un repartidor como destino', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);
    $repartidor = DeliveryPerson::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->post(route('production-orders.instant.store'), [
            'destination_type' => 'delivery_person',
            'destination_id' => $repartidor->id,
            'items' => [['product_id' => $product->id, 'quantity' => 12]],
        ])
        ->assertRedirect();

    $order = ProductionOrder::where('tenant_id', $tenant->id)->with('productionOrderRequests')->first();
    expect($order->productionOrderRequests->first()->destination_id)->toBe($repartidor->id);
});

test('la orden instantánea numera la orden y su pedido', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);

    $this->actingAs($user)->post(route('production-orders.instant.store'), [
        'destination_type' => 'location',
        'destination_id' => $tenant->defaultLocation()->id,
        'items' => [['product_id' => $product->id, 'quantity' => 12]],
    ]);

    $order = ProductionOrder::where('tenant_id', $tenant->id)->first();
    expect($order->number)->toBe(1)
        ->and($order->productionOrderRequests()->first()->position)->toBe(1);
});

test('dos renglones del mismo artículo se suman en una sola producción', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);

    $this->actingAs($user)->post(route('production-orders.instant.store'), [
        'destination_type' => 'location',
        'destination_id' => $tenant->defaultLocation()->id,
        'items' => [
            ['product_id' => $product->id, 'quantity' => 10],
            ['product_id' => $product->id, 'quantity' => 14],
        ],
    ]);

    $order = ProductionOrder::where('tenant_id', $tenant->id)->with('productions')->first();
    expect($order->productions)->toHaveCount(1)
        ->and((float) $order->productions->first()->quantity)->toBe(24.0);
});

test('la orden instantánea rechaza un destino de otro negocio', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);
    $foreignLocation = Location::factory()->for(Tenant::factory()->create())->create();

    $this->actingAs($user)
        ->post(route('production-orders.instant.store'), [
            'destination_type' => 'location',
            'destination_id' => $foreignLocation->id,
            'items' => [['product_id' => $product->id, 'quantity' => 12]],
        ])
        ->assertSessionHasErrors('destination_id');
});

test('la orden instantánea rechaza un artículo de reventa', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $resale = Product::factory()->for($tenant)->resale()->create();

    $this->actingAs($user)
        ->post(route('production-orders.instant.store'), [
            'destination_type' => 'location',
            'destination_id' => $tenant->defaultLocation()->id,
            'items' => [['product_id' => $resale->id, 'quantity' => 5]],
        ])
        ->assertSessionHasErrors('items.0.product_id');
});

test('la orden instantánea rechaza una cantidad en cero', function () {
    [$user, $tenant, $product] = productionSetup();

    $this->actingAs($user)
        ->post(route('production-orders.instant.store'), [
            'destination_type' => 'location',
            'destination_id' => $tenant->defaultLocation()->id,
            'items' => [['product_id' => $product->id, 'quantity' => 0]],
        ])
        ->assertSessionHasErrors('items.0.quantity');
});

test('la orden instantánea exige al menos un artículo', function () {
    [$user, $tenant] = productionSetup();

    $this->actingAs($user)
        ->post(route('production-orders.instant.store'), [
            'destination_type' => 'location',
            'destination_id' => $tenant->defaultLocation()->id,
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});

test('el preview de la orden instantánea devuelve el consumo combinado sin escribir nada', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 700, $user);

    $this->actingAs($user)
        ->postJson(route('production-orders.instant.preview'), ['items' => [['product_id' => $product->id, 'quantity' => 24]]])
        ->assertOk()
        ->assertJsonStructure(['lines', 'material_cost', 'labor_cost', 'total_cost'])
        ->assertJsonPath('lines.0.name', 'Harina');

    expect((float) app(StockService::class)->levelFor($harina->fresh(), $tenant->defaultLocation())->quantity)->toBe(700.0);
});

test('el preview de la orden instantánea con dos artículos distintos no lazy-carga la receta', function () {
    // Ancla de un bug real encontrado a mano en el navegador: pairsFrom() no
    // precargaba 'recipe' y explotaba con LazyLoadingViolationException en
    // cuanto había más de un artículo (o cualquiera, en los hechos) en el
    // preview — sólo se veía con datos reales, nunca con un solo artículo
    // simple como el resto de esta suite.
    [$user, $tenant, $productA, $harina] = productionSetup();
    $recipeB = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $recipeB->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 200, 'unit' => Unit::Gramo->value]);
    $productB = manufacturedProduct($tenant, $recipeB);
    $productB->update(['product_category_id' => $productA->product_category_id]);
    seedStock($harina, 5000, $user);

    $this->actingAs($user)
        ->postJson(route('production-orders.instant.preview'), [
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 12],
                ['product_id' => $productB->id, 'quantity' => 5],
            ],
        ])
        ->assertOk()
        ->assertJsonStructure(['lines', 'material_cost', 'labor_cost', 'total_cost'])
        ->assertJsonPath('lines.0.name', 'Harina');
});

test('un viewer no puede abrir la pantalla de orden instantánea', function () {
    [$user] = productionSetup(TenantUserRole::Viewer);

    $this->actingAs($user)->get(route('production-orders.instant.create'))->assertForbidden();
});

test('un viewer no puede producir una orden instantánea', function () {
    [$user, $tenant, $product] = productionSetup(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->post(route('production-orders.instant.store'), [
            'destination_type' => 'location',
            'destination_id' => $tenant->defaultLocation()->id,
            'items' => [['product_id' => $product->id, 'quantity' => 12]],
        ])
        ->assertForbidden();
});

test('anular una orden instantánea revierte el stock', function () {
    [$user, $tenant, $product, $harina] = productionSetup();
    seedStock($harina, 5000, $user);
    $location = $tenant->defaultLocation();

    $this->actingAs($user)->post(route('production-orders.instant.store'), [
        'destination_type' => 'location',
        'destination_id' => $location->id,
        'items' => [['product_id' => $product->id, 'quantity' => 24]],
    ]);
    $order = ProductionOrder::where('tenant_id', $tenant->id)->first();

    $this->actingAs($user)->patch(route('production-orders.cancel', $order))->assertRedirect();

    expect((float) app(StockService::class)->levelFor($harina->fresh(), $location)->quantity)->toBe(5000.0);
});

test('anular una orden instantánea con dos artículos no lazy-carga el tenant de cada producción', function () {
    // Ancla de otro bug real encontrado a mano: ProductionService::cancel()
    // leía $production->tenant (relación) para resolver la alerta de salto de
    // costo. Con UNA sola producción en la orden, Eloquent nunca activa su
    // guarda de lazy-loading (sólo lo hace al hidratar 2+ filas de una,
    // Builder::hydrate()) y el bug queda invisible; con dos productos en la
    // misma orden, la orden tiene 2 Production y el guard sí se activa.
    [$user, $tenant, $productA, $harina] = productionSetup();
    $recipeB = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Unidad->value]);
    $recipeB->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 200, 'unit' => Unit::Gramo->value]);
    $productB = manufacturedProduct($tenant, $recipeB);
    $productB->update(['product_category_id' => $productA->product_category_id]);
    seedStock($harina, 5000, $user);
    $location = $tenant->defaultLocation();

    $this->actingAs($user)->post(route('production-orders.instant.store'), [
        'destination_type' => 'location',
        'destination_id' => $location->id,
        'items' => [
            ['product_id' => $productA->id, 'quantity' => 12],
            ['product_id' => $productB->id, 'quantity' => 5],
        ],
    ]);
    $order = ProductionOrder::where('tenant_id', $tenant->id)->first();

    $this->actingAs($user)->patch(route('production-orders.cancel', $order))->assertRedirect();

    expect($order->fresh()->isCancelled())->toBeTrue();
});

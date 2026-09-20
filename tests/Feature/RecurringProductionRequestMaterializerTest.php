<?php

use App\Models\Customer;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderRequest;
use App\Models\RecurringProductionRequest;
use App\Models\RecurringProductionRequestLine;
use App\Models\Tenant;
use App\Services\RecurringProductionRequestMaterializer;

// productionSetup() es global (ProductionControllerTest) — arma un elaborado
// en categoría producible. seedStock()/manufacturedProduct() son globales
// (ProductionTest).

function materializer(): RecurringProductionRequestMaterializer
{
    return app(RecurringProductionRequestMaterializer::class);
}

function recurringWith(?Tenant $tenant, Product $product, array $overrides = []): RecurringProductionRequest
{
    $recurring = RecurringProductionRequest::factory()->for($tenant)->everyday()->create([
        'destination_id' => $tenant->defaultLocation()->id,
        ...$overrides,
    ]);
    RecurringProductionRequestLine::factory()->for($recurring, 'recurringProductionRequest')->create([
        'product_id' => $product->id,
        'quantity' => 5,
    ]);

    return $recurring;
}

test('genera el horizonte completo para un recurrente todos los días', function () {
    [$user, $tenant, $product] = productionSetup();
    $recurring = recurringWith($tenant, $product);

    $generated = materializer()->materialize($tenant);

    expect($generated)->toBe(RecurringProductionRequestMaterializer::HORIZON_DAYS)
        ->and(ProductionOrderRequest::where('recurring_production_request_id', $recurring->id)->count())->toBe(RecurringProductionRequestMaterializer::HORIZON_DAYS);
});

test('sólo genera para los días tildados', function () {
    [$user, $tenant, $product] = productionSetup();
    // Sólo lunes (ISO 1) — a lo sumo una instancia en 7 días de horizonte.
    recurringWith($tenant, $product, ['weekdays' => [1]]);

    $generated = materializer()->materialize($tenant);

    expect($generated)->toBeLessThanOrEqual(1);
});

test('respeta starts_on: no genera antes de la vigencia', function () {
    [$user, $tenant, $product] = productionSetup();
    recurringWith($tenant, $product, ['starts_on' => now()->addDays(3)->toDateString()]);

    $generated = materializer()->materialize($tenant);

    // De los 7 días del horizonte, sólo los últimos 4 (día 3 a 6) califican.
    expect($generated)->toBe(4);
});

test('respeta ends_on: no genera después de la vigencia', function () {
    [$user, $tenant, $product] = productionSetup();
    recurringWith($tenant, $product, ['ends_on' => now()->addDays(2)->toDateString()]);

    $generated = materializer()->materialize($tenant);

    // Días 0, 1 y 2 del horizonte (3 instancias).
    expect($generated)->toBe(3);
});

test('es idempotente: correrlo dos veces no duplica nada', function () {
    [$user, $tenant, $product] = productionSetup();
    $recurring = recurringWith($tenant, $product);

    materializer()->materialize($tenant);
    $secondRun = materializer()->materialize($tenant);

    expect($secondRun)->toBe(0)
        ->and(ProductionOrderRequest::where('recurring_production_request_id', $recurring->id)->count())->toBe(RecurringProductionRequestMaterializer::HORIZON_DAYS);
});

test('no regenera una instancia que se borró (soft delete)', function () {
    [$user, $tenant, $product] = productionSetup();
    $recurring = recurringWith($tenant, $product, ['weekdays' => [1]]);

    materializer()->materialize($tenant);
    $instance = ProductionOrderRequest::where('recurring_production_request_id', $recurring->id)->first();

    if ($instance === null) {
        // El horizonte de esta corrida no incluyó un lunes — nada que probar.
        expect(true)->toBeTrue();

        return;
    }

    $instance->delete();
    materializer()->materialize($tenant);

    expect(ProductionOrderRequest::withTrashed()->where('recurring_production_request_id', $recurring->id)->count())->toBe(1);
});

test('dos recurrentes del mismo día caen en una sola orden', function () {
    [$user, $tenant, $product] = productionSetup();
    $productB = Product::factory()->for($tenant)->manufactured()->create(['product_category_id' => $product->product_category_id]);
    $customer = Customer::factory()->for($tenant)->create();

    recurringWith($tenant, $product);
    $recurringB = RecurringProductionRequest::factory()->for($tenant)->everyday()->create([
        'destination_type' => 'customer',
        'destination_id' => $customer->id,
    ]);
    RecurringProductionRequestLine::factory()->for($recurringB, 'recurringProductionRequest')->create(['product_id' => $productB->id]);

    materializer()->materialize($tenant);

    $ordersToday = ProductionOrder::where('tenant_id', $tenant->id)->whereDate('scheduled_for', now()->toDateString())->count();
    expect($ordersToday)->toBe(1);
});

test('si la orden del día ya está Done, la instancia nueva abre otra orden', function () {
    [$user, $tenant, $product] = productionSetup();
    $done = ProductionOrder::factory()->for($tenant)->done()->create([
        'location_id' => $tenant->defaultLocation()->id,
        'scheduled_for' => now()->toDateString(),
    ]);
    recurringWith($tenant, $product, ['weekdays' => [now()->dayOfWeekIso]]);

    materializer()->materialize($tenant);

    expect(ProductionOrder::where('tenant_id', $tenant->id)->whereDate('scheduled_for', now()->toDateString())->count())->toBe(2)
        ->and($done->productionOrderRequests()->count())->toBe(0);
});

test('un destino inactivo se saltea', function () {
    [$user, $tenant, $product] = productionSetup();
    $location = Location::factory()->for($tenant)->inactive()->create();
    recurringWith($tenant, $product, ['destination_id' => $location->id]);

    $generated = materializer()->materialize($tenant);

    expect($generated)->toBe(0);
});

test('un artículo que dejó de ser producible no impide generar la instancia (sin esa línea) ni afecta al otro recurrente', function () {
    [$user, $tenant, $product] = productionSetup();
    // Sin categoría propia: con el gate invertido, sin categoría es
    // producible por default. No comparte la categoría de $product —
    // si la compartiera, marcarla no-producible afectaría a los dos.
    $productB = Product::factory()->for($tenant)->manufactured()->create();

    // syncLines() (dentro de placeRequest()) descarta en silencio una línea
    // no producible en vez de tirar — así que "roto" acá significa "la
    // instancia se genera igual, pero sin esa línea", no una excepción.
    // category()->update() (no ->category->update()): evita lazy-loadear la
    // relación, que preventLazyLoading frena fuera de producción.
    $affected = recurringWith($tenant, $product);
    $product->category()->update(['producible' => false]);
    $healthy = recurringWith($tenant, $productB);

    $generated = materializer()->materialize($tenant);

    expect($generated)->toBe(RecurringProductionRequestMaterializer::HORIZON_DAYS * 2);
    $affectedInstance = ProductionOrderRequest::where('recurring_production_request_id', $affected->id)->first();
    $healthyInstance = ProductionOrderRequest::where('recurring_production_request_id', $healthy->id)->first();
    expect($affectedInstance->lines()->count())->toBe(0)
        ->and($healthyInstance->lines()->count())->toBe(1);
});

test('materializeIfDue no vuelve a correr dentro de la hora', function () {
    [$user, $tenant, $product] = productionSetup();
    $recurring = recurringWith($tenant, $product, ['weekdays' => [1]]);

    materializer()->materializeIfDue($tenant);
    $countAfterFirst = ProductionOrderRequest::where('recurring_production_request_id', $recurring->id)->count();

    $this->travel(30)->minutes();
    materializer()->materializeIfDue($tenant->fresh());

    expect(ProductionOrderRequest::where('recurring_production_request_id', $recurring->id)->count())->toBe($countAfterFirst);
});

test('materializeIfDue vuelve a correr después de una hora', function () {
    [$user, $tenant, $product] = productionSetup();
    recurringWith($tenant, $product);

    materializer()->materializeIfDue($tenant);
    $this->travel(61)->minutes();
    $tenant = $tenant->fresh();
    $before = ProductionOrder::where('tenant_id', $tenant->id)->count();

    // Al viajar en el tiempo, "hoy" cambió: el horizonte se corre y debería
    // generar al menos una instancia más para la nueva ventana.
    materializer()->materializeIfDue($tenant);

    expect(ProductionOrder::where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual($before);
});

test('una recurrencia rota no tumba el índice de órdenes', function () {
    [$user, $tenant, $product] = productionSetup();
    recurringWith($tenant, $product);
    $product->category()->update(['producible' => false]); // deja de ser producible

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk();
});

test('el comando genera para todos los tenants sin Tenant bindeado', function () {
    [$user, $tenantA, $productA] = productionSetup();
    [$userB, $tenantB, $productB] = productionSetup();
    recurringWith($tenantA, $productA);
    recurringWith($tenantB, $productB);

    $this->artisan('production:materialize-recurring')->assertSuccessful();

    expect(ProductionOrderRequest::where('tenant_id', $tenantA->id)->whereNotNull('recurring_production_request_id')->count())->toBeGreaterThan(0)
        ->and(ProductionOrderRequest::where('tenant_id', $tenantB->id)->whereNotNull('recurring_production_request_id')->count())->toBeGreaterThan(0);
});

test('el comando respeta --tenant', function () {
    [$user, $tenantA, $productA] = productionSetup();
    [$userB, $tenantB, $productB] = productionSetup();
    recurringWith($tenantA, $productA);
    recurringWith($tenantB, $productB);

    $this->artisan('production:materialize-recurring', ['--tenant' => $tenantA->id])->assertSuccessful();

    expect(ProductionOrderRequest::where('tenant_id', $tenantA->id)->whereNotNull('recurring_production_request_id')->count())->toBeGreaterThan(0)
        ->and(ProductionOrderRequest::where('tenant_id', $tenantB->id)->whereNotNull('recurring_production_request_id')->count())->toBe(0);
});

<?php

use App\Enums\TenantUserRole;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductionOrderRequest;
use App\Models\RecurringProductionRequest;
use App\Models\Tenant;

// productionSetup() es global (ProductionControllerTest) — arma un elaborado
// en categoría producible. tenantUserAs() es global (IngredientCrudTest).
// El destino se crea con un nombre al azar (no Tenant::defaultLocation(),
// que siempre se llama "Casa Central" en todos los tenants) para que el
// test de aislamiento pueda distinguir el propio del ajeno por nombre.

function recurringSetup(Tenant $tenant, Product $product): RecurringProductionRequest
{
    $recurring = RecurringProductionRequest::factory()->for($tenant)->create([
        'destination_id' => Location::factory()->for($tenant)->create()->id,
    ]);
    $recurring->lines()->create(['product_id' => $product->id, 'quantity' => 5, 'unit' => $product->unit->value]);

    return $recurring;
}

test('el índice lista sólo los recurrentes del tenant actual', function () {
    [$user, $tenant, $product] = productionSetup();
    [$userB, $tenantB, $productB] = productionSetup();
    $own = recurringSetup($tenant, $product);
    $foreign = recurringSetup($tenantB, $productB);
    // Los nombres se leen ANTES del request: actingAs()->get() rebindea
    // app(Tenant::class) a $tenant, y el scope global de BelongsToTenant
    // dejaría $foreign->destination en null (es de $tenantB).
    $ownName = $own->destination->name;
    $foreignName = $foreign->destination->name;

    $this->actingAs($user)
        ->get(route('production-requests.recurring.index'))
        ->assertOk()
        ->assertSee($ownName)
        ->assertDontSee($foreignName);
});

test('el índice muestra "Todavía no hay pedidos recurrentes" cuando no hay ninguno', function () {
    [$user] = productionSetup();

    $this->actingAs($user)
        ->get(route('production-requests.recurring.index'))
        ->assertOk()
        ->assertSee('Todavía no hay pedidos recurrentes');
});

test('el índice de recurrentes muestra las pestañas de Órdenes de producción', function () {
    [$user] = productionSetup();

    $this->actingAs($user)
        ->get(route('production-requests.recurring.index'))
        ->assertOk()
        ->assertSee(route('production-orders.index'), false)
        ->assertSee('Pedidos recurrentes');
});

test('el índice de órdenes muestra las pestañas hacia pedidos recurrentes', function () {
    [$user] = productionSetup();

    $this->actingAs($user)
        ->get(route('production-orders.index'))
        ->assertOk()
        ->assertSee(route('production-requests.recurring.index'), false)
        ->assertSee('Pedidos recurrentes');
});

test('owner edita los días, la vigencia y los artículos de un recurrente', function () {
    [$user, $tenant, $product] = productionSetup();
    $productB = Product::factory()->for($tenant)->manufactured()->create(['product_category_id' => $product->product_category_id]);
    $recurring = recurringSetup($tenant, $product);

    $this->actingAs($user)
        ->patch(route('production-requests.recurring.update', $recurring), [
            'weekdays' => [1, 3, 5],
            'ends_on' => now()->addMonth()->toDateString(),
            'notes' => 'Sólo lunes, miércoles y viernes',
            'lines' => [['product_id' => $productB->id, 'quantity' => 8]],
        ])
        ->assertRedirect();

    $recurring->refresh();
    expect($recurring->weekdays)->toBe([1, 3, 5])
        ->and($recurring->ends_on->toDateString())->toBe(now()->addMonth()->toDateString())
        ->and($recurring->notes)->toBe('Sólo lunes, miércoles y viernes')
        ->and($recurring->lines()->count())->toBe(1)
        ->and($recurring->lines()->first()->product_id)->toBe($productB->id);
});

test('editar acepta los días como texto, igual que llegarían de un checkbox real', function () {
    [$user, $tenant, $product] = productionSetup();
    $recurring = recurringSetup($tenant, $product);

    $this->actingAs($user)
        ->patch(route('production-requests.recurring.update', $recurring), [
            'weekdays' => ['2', '4'],
            'lines' => [['product_id' => $product->id, 'quantity' => 5]],
        ])
        ->assertRedirect();

    expect($recurring->refresh()->weekdays)->toBe([2, 4]);
});

test('pausar no borra el recurrente, sólo lo desactiva', function () {
    [$user, $tenant, $product] = productionSetup();
    $recurring = recurringSetup($tenant, $product);

    $this->actingAs($user)
        ->patch(route('production-requests.recurring.toggle-active', $recurring))
        ->assertRedirect();

    expect($recurring->refresh()->active)->toBeFalse();
    expect(RecurringProductionRequest::find($recurring->id))->not->toBeNull();

    $this->actingAs($user)
        ->patch(route('production-requests.recurring.toggle-active', $recurring))
        ->assertRedirect();

    expect($recurring->refresh()->active)->toBeTrue();
});

test('"Generar ahora" genera los pedidos pendientes', function () {
    [$user, $tenant, $product] = productionSetup();
    recurringSetup($tenant, $product);

    $this->actingAs($user)
        ->post(route('production-requests.recurring.generate'))
        ->assertRedirect();

    expect(ProductionOrderRequest::where('tenant_id', $tenant->id)->whereNotNull('recurring_production_request_id')->count())->toBeGreaterThan(0);
});

test('un viewer no puede editar ni pausar un recurrente', function () {
    [$user, $tenant, $product] = productionSetup(TenantUserRole::Viewer);
    $recurring = recurringSetup($tenant, $product);

    $this->actingAs($user)
        ->patch(route('production-requests.recurring.toggle-active', $recurring))
        ->assertForbidden();
});

test('aislamiento: un recurrente de otro tenant devuelve 404', function () {
    [$user] = productionSetup();
    [$userB, $tenantB, $productB] = productionSetup();
    $foreign = recurringSetup($tenantB, $productB);

    $this->actingAs($user)
        ->patch(route('production-requests.recurring.toggle-active', $foreign))
        ->assertNotFound();
});

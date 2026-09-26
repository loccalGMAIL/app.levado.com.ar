<?php

use App\Enums\DeliveryDestinationType;
use App\Enums\TenantUserRole;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\ProductionOrderService;

// stockTenantUser() es global (StockServiceTest); manufacturedProduct() y
// seedStock() son globales (ProductionTest); productionService() es global (ProductionTest).

/** Elaborado simple (1 ingrediente, rendimiento 1 u) listo para producir. */
function historyProduct($tenant)
{
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => 'gr', 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => 'u']);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 100, 'unit' => 'gr']);

    return manufacturedProduct($tenant, $recipe);
}

test('la pestaña Historial lista las producciones de todos los artículos', function () {
    [$user, $tenant] = stockTenantUser();
    $productA = historyProduct($tenant);
    $productB = historyProduct($tenant);
    productionService()->produce($productA, 1, null, $user);
    productionService()->produce($productB, 1, null, $user);

    $this->actingAs($user)
        ->get(route('products.history'))
        ->assertOk()
        ->assertSee($productA->name)
        ->assertSee($productB->name);
});

test('el historial ordena de la más reciente a la más vieja', function () {
    [$user, $tenant] = stockTenantUser();
    $oldProduct = historyProduct($tenant);
    $recentProduct = historyProduct($tenant);
    $old = productionService()->produce($oldProduct, 1, null, $user);
    $old->update(['produced_at' => now()->subDay()]);
    productionService()->produce($recentProduct, 1, null, $user);

    $this->actingAs($user)
        ->get(route('products.history'))
        ->assertOk()
        ->assertSeeInOrder([$recentProduct->name, $oldProduct->name]);
});

test('el historial filtra por artículo', function () {
    [$user, $tenant] = stockTenantUser();
    $productA = historyProduct($tenant);
    $productB = historyProduct($tenant);
    productionService()->produce($productA, 1, null, $user);
    $productionB = productionService()->produce($productB, 1, null, $user);

    // No se busca la ausencia del NOMBRE: el <select> del filtro lista todos
    // los artículos igual, así que $productB->name sigue apareciendo ahí.
    // Se busca la ausencia de su fila (el link a su producción puntual).
    $this->actingAs($user)
        ->get(route('products.history', ['product' => $productA->id]))
        ->assertOk()
        ->assertSee($productA->name)
        ->assertDontSee(route('production.show', $productionB));
});

test('el historial filtra por estado anulada', function () {
    [$user, $tenant] = stockTenantUser();
    $product = historyProduct($tenant);
    $confirmed = productionService()->produce($product, 1, null, $user);
    $cancelled = productionService()->produce($product, 1, null, $user);
    productionService()->cancel($cancelled->fresh(), $user);

    $html = $this->actingAs($user)
        ->get(route('products.history', ['status' => 'cancelled']))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'Anulada'))->toBeGreaterThan(0);
    $this->get(route('products.history', ['status' => 'confirmed']))->assertOk();
    expect($confirmed->id)->not->toBe($cancelled->id);
});

test('el historial filtra por rango de fechas', function () {
    [$user, $tenant] = stockTenantUser();
    $product = historyProduct($tenant);
    $old = productionService()->produce($product, 1, null, $user);
    $old->update(['produced_at' => now()->subDays(10)]);
    $recent = productionService()->produce($product, 1, null, $user);

    $this->actingAs($user)
        ->get(route('products.history', ['from' => now()->subDay()->toDateString()]))
        ->assertOk()
        ->assertSee(route('production.show', $recent))
        ->assertDontSee(route('production.show', $old));
});

test('el historial muestra Ad-hoc cuando la producción no vino de una orden', function () {
    [$user, $tenant] = stockTenantUser();
    $product = historyProduct($tenant);
    productionService()->produce($product, 1, null, $user);

    $this->actingAs($user)
        ->get(route('products.history'))
        ->assertOk()
        ->assertSee('Ad-hoc');
});

test('el historial linkea a la orden cuando la producción vino de una', function () {
    [$user, $tenant] = stockTenantUser();
    $product = historyProduct($tenant);
    $order = app(ProductionOrderService::class)->produceInstant(
        $tenant,
        $user,
        DeliveryDestinationType::Location,
        $tenant->defaultLocation()->id,
        collect([['product' => $product, 'quantity' => 1]]),
    );

    $this->actingAs($user)
        ->get(route('products.history'))
        ->assertOk()
        ->assertSee($order->numberLabel());
});

test('el historial conserva los filtros al paginar', function () {
    [$user, $tenant] = stockTenantUser();
    $product = historyProduct($tenant);
    // 21 producciones: supera el paginate(20) y fuerza que se rendericen
    // los links de paginación (hasPages() === true).
    for ($i = 0; $i < 21; $i++) {
        productionService()->produce($product, 1, null, $user);
    }

    $html = $this->actingAs($user)
        ->get(route('products.history', ['product' => $product->id]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('product='.$product->id.'&amp;page=2');
});

test('el historial no muestra producciones de otro negocio', function () {
    [$user, $tenant] = stockTenantUser();
    $otherTenant = Tenant::factory()->create();
    $foreignProduct = historyProduct($otherTenant);
    $otherUser = User::factory()->create();
    TenantUser::create(['tenant_id' => $otherTenant->id, 'user_id' => $otherUser->id, 'role' => 'owner', 'active' => true]);
    productionService()->produce($foreignProduct, 1, null, $otherUser);

    $this->actingAs($user)
        ->get(route('products.history'))
        ->assertOk()
        ->assertDontSee($foreignProduct->name);
});

test('un viewer puede abrir el historial', function () {
    [$user] = stockTenantUser(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->get(route('products.history'))
        ->assertOk();
});

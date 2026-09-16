<?php

use App\Enums\NotificationType;
use App\Models\Ingredient;
use App\Models\Notification;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLine;
use App\Models\ProductionOrderRequest;
use App\Models\Recipe;

// stockTenantUser() es global (StockServiceTest); manufacturedProduct(),
// seedStock() y productionService() son globales (ProductionTest);
// productionOrderService() es global (ProductionOrderTest).

/** Receta simple (1 ingrediente, rendimiento 10 u) con el costo dado en el ingrediente. */
function costSpikeRecipe($tenant, float $ingredientCost): Recipe
{
    $harina = Ingredient::factory()->for($tenant)->create(['unit' => 'gr', 'cost_per_unit' => $ingredientCost]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 10, 'yield_unit' => 'u']);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 1000, 'unit' => 'gr']);

    return $recipe;
}

test('producir más caro que la vez anterior levanta una alerta de salto de costo', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = costSpikeRecipe($tenant, 1); // 1000 gr × $1 = $1.000 → unit_cost 100
    $product = manufacturedProduct($tenant, $recipe);

    productionService()->produce($product, 10, null, $user);

    $recipe->ingredientLines->first()->ingredient->update(['cost_per_unit' => 2]); // ahora $2.000 → unit_cost 200 (+100%)
    // ->fresh(): $product cachea recipe/ingredientLines en el primer produce(); sin
    // recargar, el segundo vería el costo viejo (mismo artefacto de reuso de objeto
    // que en un request real no existe, porque cada request parte de un modelo nuevo).
    $production = productionService()->produce($product->fresh(), 10, null, $user);

    $spike = Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->first();

    expect($spike)->not->toBeNull()
        ->and($spike->title)->toContain($product->name)
        ->and($spike->dedupe_key)->toBe("cost_spike:production:{$production->id}");
});

test('la primera producción de un artículo no levanta alerta', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = costSpikeRecipe($tenant, 1);
    $product = manufacturedProduct($tenant, $recipe);

    productionService()->produce($product, 10, null, $user);

    expect(Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->count())->toBe(0);
});

test('producir más barato que la vez anterior no levanta alerta', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = costSpikeRecipe($tenant, 2);
    $product = manufacturedProduct($tenant, $recipe);

    productionService()->produce($product, 10, null, $user);
    $recipe->ingredientLines->first()->ingredient->update(['cost_per_unit' => 1]); // -50%
    productionService()->produce($product->fresh(), 10, null, $user);

    expect(Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->count())->toBe(0);
});

test('una suba por debajo del umbral no levanta alerta', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = costSpikeRecipe($tenant, 1);
    $product = manufacturedProduct($tenant, $recipe);

    productionService()->produce($product, 10, null, $user);
    $recipe->ingredientLines->first()->ingredient->update(['cost_per_unit' => 1.1]); // +10%, bajo el umbral de 15%
    productionService()->produce($product->fresh(), 10, null, $user);

    expect(Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->count())->toBe(0);
});

test('una producción anulada no sirve de referencia para la siguiente', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = costSpikeRecipe($tenant, 1);
    $product = manufacturedProduct($tenant, $recipe);

    $first = productionService()->produce($product, 10, null, $user);
    $recipe->ingredientLines->first()->ingredient->update(['cost_per_unit' => 2]);
    productionService()->cancel($first->fresh(), $user);

    // La única producción confirmada fue anulada: no hay baseline vivo.
    productionService()->produce($product->fresh(), 10, null, $user);

    expect(Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->count())->toBe(0);
});

test('la alerta enlaza a la producción que la generó', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = costSpikeRecipe($tenant, 1);
    $product = manufacturedProduct($tenant, $recipe);

    productionService()->produce($product, 10, null, $user);
    $recipe->ingredientLines->first()->ingredient->update(['cost_per_unit' => 2]);
    $production = productionService()->produce($product->fresh(), 10, null, $user);

    $spike = Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->first();

    expect($spike->action_url)->toBe(route('production.show', $production))
        ->and($spike->subject_type)->toBe('product')
        ->and($spike->subject_id)->toBe($product->id);
});

test('cada producción levanta a lo sumo una alerta', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = costSpikeRecipe($tenant, 1);
    $product = manufacturedProduct($tenant, $recipe);

    productionService()->produce($product, 10, null, $user);
    $recipe->ingredientLines->first()->ingredient->update(['cost_per_unit' => 2]);
    productionService()->produce($product->fresh(), 10, null, $user);

    expect(Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->count())->toBe(1);
});

test('con la alerta de salto de costo apagada, producir no notifica', function () {
    [$user, $tenant] = stockTenantUser();
    $tenant->setSetting('alerts.cost_spike.enabled', '0');
    $recipe = costSpikeRecipe($tenant, 1);
    $product = manufacturedProduct($tenant, $recipe);

    productionService()->produce($product, 10, null, $user);
    $recipe->ingredientLines->first()->ingredient->update(['cost_per_unit' => 2]);
    productionService()->produce($product->fresh(), 10, null, $user);

    expect(Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->count())->toBe(0);
});

test('una orden de producción levanta a lo sumo una alerta por artículo', function () {
    [$user, $tenant] = stockTenantUser();
    $recipeA = costSpikeRecipe($tenant, 1);
    $productA = manufacturedProduct($tenant, $recipeA);
    $recipeB = costSpikeRecipe($tenant, 1);
    $productB = manufacturedProduct($tenant, $recipeB);

    productionService()->produce($productA, 10, null, $user);
    productionService()->produce($productB, 10, null, $user);
    $recipeA->ingredientLines->first()->ingredient->update(['cost_per_unit' => 2]);
    $recipeB->ingredientLines->first()->ingredient->update(['cost_per_unit' => 2]);

    $order = ProductionOrder::factory()->for($tenant)->confirmed()->create([
        'location_id' => $tenant->defaultLocation()->id,
    ]);
    $request = ProductionOrderRequest::factory()->for($tenant)->create([
        'production_order_id' => $order->id,
        'destination_type' => 'location',
        'destination_id' => $tenant->defaultLocation()->id,
    ]);
    ProductionOrderLine::factory()->create([
        'production_order_request_id' => $request->id,
        'product_id' => $productA->id,
        'quantity' => 10,
        'unit' => $productA->unit->value,
    ]);
    ProductionOrderLine::factory()->create([
        'production_order_request_id' => $request->id,
        'product_id' => $productB->id,
        'quantity' => 10,
        'unit' => $productB->unit->value,
    ]);

    productionOrderService()->produce($order->fresh(), $user);

    expect(Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->count())->toBe(2);
});

test('anular una producción resuelve su alerta de salto de costo', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = costSpikeRecipe($tenant, 1);
    $product = manufacturedProduct($tenant, $recipe);

    productionService()->produce($product, 10, null, $user);
    $recipe->ingredientLines->first()->ingredient->update(['cost_per_unit' => 2]);
    $production = productionService()->produce($product->fresh(), 10, null, $user);

    expect(Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->whereNull('resolved_at')->count())->toBe(1);

    productionService()->cancel($production->fresh(), $user);

    expect(Notification::where('tenant_id', $tenant->id)->where('type', NotificationType::CostSpike->value)->whereNull('resolved_at')->count())->toBe(0);
});

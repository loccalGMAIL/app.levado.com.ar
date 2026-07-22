<?php

use App\Enums\ProductionStatus;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\Recipe;
use App\Services\ProductionService;
use App\Services\StockService;
use Symfony\Component\HttpKernel\Exception\HttpException;

// stockTenantUser() y stockService() son helpers globales (definidos en StockServiceTest).

function productionService(): ProductionService
{
    return app(ProductionService::class);
}

/** Producto elaborado (unidad u) ligado a una receta, en el tenant dado. */
function manufacturedProduct($tenant, Recipe $recipe): Product
{
    return Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
        'recipe_id' => $recipe->id,
    ]);
}

/** Deja stock inicial de un ítem en la sucursal default del tenant. */
function seedStock(Ingredient|Packaging|Product $item, float $quantity, $user): void
{
    app(StockService::class)->registerAdjustment($item, $item->tenant->defaultLocation(), $quantity, 'Carga inicial test', $user);
}

// --- Happy path: descuenta insumos (con conversión de unidad) y suma el elaborado ---

test('producir descuenta ingredientes y descartables por el factor correcto y suma el elaborado', function () {
    [$user, $tenant] = stockTenantUser();

    $harina = Ingredient::factory()->for($tenant)->create(['name' => 'Harina', 'unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $manteca = Ingredient::factory()->for($tenant)->create(['name' => 'Manteca', 'unit' => Unit::Gramo->value, 'cost_per_unit' => 0.02]);
    $caja = Packaging::factory()->for($tenant)->create(['name' => 'Caja', 'cost_per_unit' => 5]);

    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $manteca->id, 'quantity' => 0.3, 'unit' => Unit::Kilogramo->value]); // 300 gr, prueba conversión
    $recipe->packagingLines()->create(['packaging_id' => $caja->id, 'quantity' => 1]);

    $product = manufacturedProduct($tenant, $recipe);

    seedStock($harina, 5000, $user);
    seedStock($manteca, 5000, $user);
    seedStock($caja, 10, $user);

    $production = productionService()->produce($product, 24, null, $user); // factor = 24/12 = 2

    expect($production->status)->toBe(ProductionStatus::Confirmed)
        ->and((float) $production->quantity)->toBe(24.0);

    $stock = app(StockService::class);
    $loc = $tenant->defaultLocation();

    expect((float) $stock->levelFor($harina, $loc)->quantity)->toBe(4000.0)   // 5000 - 500*2
        ->and((float) $stock->levelFor($manteca, $loc)->quantity)->toBe(4400.0) // 5000 - 300*2
        ->and((float) $stock->levelFor($caja, $loc)->quantity)->toBe(8.0)      // 10 - 1*2
        ->and((float) $stock->levelFor($product, $loc)->quantity)->toBe(24.0);  // +24
});

test('el costo total de la producción suma el costo físico de los insumos consumidos', function () {
    [$user, $tenant] = stockTenantUser();

    $harina = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 10, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 200, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);

    $production = productionService()->produce($product, 20, null, $user); // factor 2 → 400 gr × 0.01 = 4

    expect((float) $production->total_cost)->toBe(4.0)
        ->and((float) $production->unit_cost)->toBe(0.2); // 4 / 20
});

// --- Sub-receta phantom: se explota a insumos base, no genera movimiento propio ---

test('una sub-receta se explota a sus insumos base escalados y no se descuenta como ítem', function () {
    [$user, $tenant] = stockTenantUser();

    $harina = Ingredient::factory()->for($tenant)->create(['name' => 'Harina', 'unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);

    $masa = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_quantity' => 1, 'yield_unit' => Unit::Kilogramo->value]);
    $masa->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 800, 'unit' => Unit::Gramo->value]);

    $facturas = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $facturas->subrecipeLines()->create(['child_recipe_id' => $masa->id, 'quantity_used' => 0.5, 'unit' => Unit::Kilogramo->value]);

    $product = manufacturedProduct($tenant, $facturas);

    seedStock($harina, 5000, $user);

    $production = productionService()->produce($product, 24, null, $user); // factor 2, childFactor = 2*0.5/1 = 1 → 800 gr

    $loc = $tenant->defaultLocation();
    expect((float) app(StockService::class)->levelFor($harina, $loc)->quantity)->toBe(4200.0); // 5000 - 800

    // Solo se movieron: la salida de Harina y la entrada del producto (la sub-receta no es un ítem).
    expect($production->movements()->count())->toBe(2);
});

// --- Anulación: revierte todos los movimientos ---

test('anular una producción revierte el stock de insumos y del elaborado al estado previo', function () {
    [$user, $tenant] = stockTenantUser();

    $harina = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);

    seedStock($harina, 5000, $user);
    $stock = app(StockService::class);
    $loc = $tenant->defaultLocation();

    $production = productionService()->produce($product, 24, null, $user);
    expect((float) $stock->levelFor($harina, $loc)->quantity)->toBe(4000.0)
        ->and((float) $stock->levelFor($product, $loc)->quantity)->toBe(24.0);

    productionService()->cancel($production->fresh(), $user);

    expect($production->fresh()->status)->toBe(ProductionStatus::Cancelled)
        ->and($production->fresh()->cancelled_at)->not->toBeNull()
        ->and((float) $stock->levelFor($harina, $loc)->quantity)->toBe(5000.0) // vuelve
        ->and((float) $stock->levelFor($product, $loc)->quantity)->toBe(0.0);   // vuelve
});

test('anular dos veces es idempotente: no duplica contramovimientos', function () {
    [$user, $tenant] = stockTenantUser();

    $harina = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);

    seedStock($harina, 5000, $user);
    $production = productionService()->produce($product, 24, null, $user);

    productionService()->cancel($production->fresh(), $user);
    productionService()->cancel($production->fresh(), $user); // no-op

    $loc = $tenant->defaultLocation();
    expect((float) app(StockService::class)->levelFor($harina, $loc)->quantity)->toBe(5000.0);
});

// --- Movimientos: referencia y tipo ---

test('los movimientos de una producción quedan tipados como production y referenciados a ella', function () {
    [$user, $tenant] = stockTenantUser();

    $harina = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);

    seedStock($harina, 5000, $user);
    $production = productionService()->produce($product, 24, null, $user);

    $movements = $production->movements()->get();
    expect($movements)->toHaveCount(2)
        ->and($movements->every(fn ($m) => $m->type === StockMovementType::Production))->toBeTrue()
        ->and($movements->every(fn ($m) => $m->reference_type === 'production' && $m->reference_id === $production->id))->toBeTrue();
});

// --- Guards ---

test('no se puede producir un producto de reventa', function () {
    [$user, $tenant] = stockTenantUser();
    $product = Product::factory()->for($tenant)->resale()->create();

    expect(fn () => productionService()->produce($product, 5, null, $user))->toThrow(HttpException::class);
});

test('no se puede producir con cantidad cero o negativa', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $product = manufacturedProduct($tenant, $recipe);

    expect(fn () => productionService()->produce($product, 0, null, $user))->toThrow(HttpException::class);
});

test('no se puede producir un elaborado cuya receta está inactiva', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value, 'active' => false]);
    $product = manufacturedProduct($tenant, $recipe);

    expect(fn () => productionService()->produce($product, 5, null, $user))->toThrow(HttpException::class);
});

test('no se puede producir si la unidad del producto es incompatible con el rendimiento de la receta', function () {
    [$user, $tenant] = stockTenantUser();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1, 'yield_unit' => Unit::Kilogramo->value]);
    $product = manufacturedProduct($tenant, $recipe);

    expect(fn () => productionService()->produce($product, 5, null, $user))->toThrow(HttpException::class);
});

// --- Stock negativo permitido ---

test('producir sin stock suficiente deja el insumo en negativo (no bloquea)', function () {
    [$user, $tenant] = stockTenantUser();

    $harina = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);

    productionService()->produce($product, 24, null, $user); // sin stock previo

    $loc = $tenant->defaultLocation();
    expect((float) app(StockService::class)->levelFor($harina, $loc)->quantity)->toBe(-1000.0);
});

// --- Preview ---

test('el preview informa insumos, faltantes y costo total sin escribir stock', function () {
    [$user, $tenant] = stockTenantUser();

    $harina = Ingredient::factory()->for($tenant)->create(['name' => 'Harina', 'unit' => Unit::Gramo->value, 'cost_per_unit' => 0.01]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 12, 'yield_unit' => Unit::Unidad->value]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);
    $product = manufacturedProduct($tenant, $recipe);

    seedStock($harina, 700, $user);

    $preview = productionService()->preview($product, 24); // 1000 gr necesarios, 700 disponibles

    expect($preview['lines'])->toHaveCount(1)
        ->and($preview['lines'][0]['quantity'])->toBe(1000.0)
        ->and($preview['lines'][0]['available'])->toBe(700.0)
        ->and($preview['lines'][0]['shortfall'])->toBe(300.0)
        ->and($preview['total_cost'])->toBe(10.0); // 1000 × 0.01

    // El preview no movió stock.
    expect((float) app(StockService::class)->levelFor($harina, $tenant->defaultLocation())->quantity)->toBe(700.0);
});

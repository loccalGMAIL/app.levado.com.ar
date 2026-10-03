<?php

use App\Enums\PricingPolicy;
use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Services\ProductionService;
use App\Services\ProductPriceWriter;
use App\Services\RecipeCostPropagator;
use Symfony\Component\HttpKernel\Exception\HttpException;

// tenantUserAs() es un helper global de la suite (definido en IngredientCrudTest).

/** Receta "Facturas" (rinde 56 unidades) con costo por unidad conocido. */
function facturasRecipe($tenant, float $unitCost = 10): Recipe
{
    return Recipe::factory()->for($tenant)->create([
        'unit_cost' => $unitCost,
        'labor_hours' => 0,
        'yield_quantity' => 56,
        'yield_unit' => Unit::Unidad->value,
    ]);
}

test('el costo de una presentación es el costo por unidad de la receta por la cantidad', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = facturasRecipe($tenant, 10);
    $pack = Product::factory()->presentationOf($recipe, 6)->create();

    expect($pack->isRecipePresentation())->toBeTrue()
        ->and($pack->currentCost())->toBe(60.0);
});

test('el overhead de una presentación también se multiplica por la cantidad', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create([
        'unit_cost' => 10,
        'labor_hours' => 5.6,
        'yield_quantity' => 56,
        'yield_unit' => Unit::Unidad->value,
    ]);
    $base = Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
    ]);
    $pack = Product::factory()->presentationOf($recipe, 6)->create();

    // overhead por unidad = 5.6 h × 100 / 56 = 10
    expect($base->fullCost(100))->toBe(20.0)
        ->and($pack->fullCost(100))->toBe(120.0);
});

test('Recipe::manufacturedProduct devuelve el artículo base aunque exista un pack', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = facturasRecipe($tenant);
    $pack = Product::factory()->presentationOf($recipe, 6)->create();
    $base = Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
    ]);

    expect($recipe->manufacturedProduct->id)->toBe($base->id)
        ->and($recipe->manufacturedProducts->pluck('id')->all())->toContain($base->id, $pack->id);
});

test('las presentaciones no son producibles', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = facturasRecipe($tenant);
    $pack = Product::factory()->presentationOf($recipe, 6)->create();
    $base = Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
    ]);

    expect(Product::producible()->pluck('id')->all())->toContain($base->id)->not->toContain($pack->id);
});

test('producir una presentación se rechaza con 422', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $pack = Product::factory()->presentationOf(facturasRecipe($tenant), 6)->create();

    expect(fn () => app(ProductionService::class)->produce($pack, 1, null, $user))->toThrow(HttpException::class);
});

test('al cambiar el costo de la receta se recalcula el precio con política del pack', function () {
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Kilogramo->value, 'cost_per_unit' => 1000]);
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 10, 'yield_unit' => Unit::Unidad->value]);
    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 500,
        'unit' => Unit::Gramo->value,
    ]);
    $propagator = app(RecipeCostPropagator::class);
    $propagator->propagateFrom($recipe); // costo/u = 50

    $pack = Product::factory()->presentationOf($recipe->fresh(), 6)->create();
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($pack, $list, PricingPolicy::Markup, 50);
    expect((float) $pack->currentPrice($list))->toBe(450.0); // 6 × 50 × 1.5

    $ingredient->update(['cost_per_unit' => 2000]);
    $propagator->propagateFrom($recipe->fresh()); // costo/u = 100

    expect((float) $pack->fresh()->currentPrice($list))->toBe(900.0); // 6 × 100 × 1.5
});

test('owner puede crear una presentación indicando la cantidad de la receta', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = facturasRecipe($tenant);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Pack de medialunas',
            'type' => ProductType::Manufactured->value,
            'recipe_id' => $recipe->id,
            'recipe_quantity' => '6',
            'unit' => Unit::Unidad->value,
        ])
        ->assertRedirect(route('products.index'));

    $pack = $tenant->products()->where('name', 'Pack de medialunas')->first();

    expect((float) $pack->recipe_quantity)->toBe(6.0)
        ->and($pack->isRecipePresentation())->toBeTrue();
});

test('la cantidad de la receta debe ser mayor a cero', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = facturasRecipe($tenant);

    $this->actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Pack inválido',
            'type' => ProductType::Manufactured->value,
            'recipe_id' => $recipe->id,
            'recipe_quantity' => '0',
            'unit' => Unit::Unidad->value,
        ])
        ->assertSessionHasErrors('recipe_quantity');
});

test('pasar un artículo a reventa anula su cantidad de receta', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $pack = Product::factory()->presentationOf(facturasRecipe($tenant), 6)->create();

    $this->actingAs($user)
        ->put(route('products.update', $pack), [
            'name' => $pack->name,
            'type' => ProductType::Resale->value,
            'recipe_quantity' => '6',
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '100',
        ])
        ->assertRedirect();

    expect($pack->fresh()->recipe_quantity)->toBeNull()
        ->and($pack->fresh()->recipe_id)->toBeNull();
});

test('editar la cantidad de la receta recalcula el precio con política', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $pack = Product::factory()->presentationOf(facturasRecipe($tenant, 10), 6)->create();
    $list = $tenant->defaultPriceList();
    app(ProductPriceWriter::class)->setPolicy($pack, $list, PricingPolicy::Markup, 100);
    expect((float) $pack->currentPrice($list))->toBe(120.0); // 6 × 10 × 2

    $this->actingAs($user)
        ->put(route('products.update', $pack), [
            'name' => $pack->name,
            'type' => ProductType::Manufactured->value,
            'recipe_id' => $pack->recipe_id,
            'recipe_quantity' => '12',
            'unit' => Unit::Unidad->value,
        ])
        ->assertRedirect();

    expect((float) $pack->fresh()->currentPrice($list))->toBe(240.0); // 12 × 10 × 2
});

test('el listado muestra la cantidad de receta del pack', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $recipe = facturasRecipe($tenant);
    $recipe->update(['name' => 'FacturasZZ']);
    Product::factory()->presentationOf($recipe, 6)->create(['name' => 'PackMedialunasZZ']);

    $this->actingAs($user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('PackMedialunasZZ')
        ->assertSee('6 u de FacturasZZ');
});

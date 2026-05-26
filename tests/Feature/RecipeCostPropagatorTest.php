<?php

use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\LaborType;
use App\Models\Packaging;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeLaborLine;
use App\Models\RecipePackagingLine;
use App\Models\RecipeSubrecipeLine;
use App\Models\Tenant;
use App\Services\RecipeCostCalculator;
use App\Services\RecipeCostPropagator;
use App\Services\UnitConverter;

function makePropagator(): RecipeCostPropagator
{
    return new RecipeCostPropagator(new RecipeCostCalculator(new UnitConverter));
}

test('isAncestor returns false when no relationship exists', function () {
    $tenant = Tenant::factory()->create();
    $a = Recipe::factory()->for($tenant)->create();
    $b = Recipe::factory()->for($tenant)->create();

    expect(makePropagator()->isAncestor($a->id, $b->id, $tenant->id))->toBeFalse();
});

test('isAncestor returns true for direct parent', function () {
    $tenant = Tenant::factory()->create();
    $parent = Recipe::factory()->for($tenant)->create();
    $child = Recipe::factory()->for($tenant)->create();

    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    // parent is an ancestor of child
    expect(makePropagator()->isAncestor($parent->id, $child->id, $tenant->id))->toBeTrue();
});

test('isAncestor returns true for grandparent (transitive)', function () {
    $tenant = Tenant::factory()->create();
    $grandparent = Recipe::factory()->for($tenant)->create();
    $parent = Recipe::factory()->for($tenant)->create();
    $child = Recipe::factory()->for($tenant)->create();

    RecipeSubrecipeLine::create([
        'recipe_id' => $grandparent->id,
        'child_recipe_id' => $parent->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);
    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    expect(makePropagator()->isAncestor($grandparent->id, $child->id, $tenant->id))->toBeTrue();
});

test('isAncestor is scoped to tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $parentA = Recipe::factory()->for($tenantA)->create();
    $childA = Recipe::factory()->for($tenantA)->create();

    RecipeSubrecipeLine::create([
        'recipe_id' => $parentA->id,
        'child_recipe_id' => $childA->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    // Same IDs but queried from tenantB scope → should return false
    expect(makePropagator()->isAncestor($parentA->id, $childA->id, $tenantB->id))->toBeFalse();
});

test('propagateFrom sets unit_cost on a recipe with only ingredient lines', function () {
    $tenant = Tenant::factory()->create();
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 1000,
    ]);
    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
    ]);

    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 500,
        'unit' => Unit::Gramo->value,
    ]);

    makePropagator()->propagateFrom($recipe);

    // 500g of 1000/kg = 500 cost / 10 units = 50 per unit
    expect((float) $recipe->fresh()->unit_cost)->toBe(50.0);
});

test('propagateFrom sets unit_cost to null when yield_quantity is zero', function () {
    $tenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 0,
        'yield_unit' => Unit::Unidad->value,
    ]);

    makePropagator()->propagateFrom($recipe);

    expect($recipe->fresh()->unit_cost)->toBeNull();
});

test('propagateFrom cascades one level up to parent', function () {
    $tenant = Tenant::factory()->create();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => null,
    ]);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 400,
    ]);
    RecipeIngredientLine::create([
        'recipe_id' => $child->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $parent = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
    ]);
    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    makePropagator()->propagateFrom($child);

    // child: 1kg ingredient @400 / 1kg yield = 400/kg unit_cost
    expect((float) $child->fresh()->unit_cost)->toBe(400.0);
    // parent: 1kg of child @400 / 10 units = 40 per unit
    expect((float) $parent->fresh()->unit_cost)->toBe(40.0);
});

test('propagateFrom handles two-level chain: grandparent gets updated', function () {
    $tenant = Tenant::factory()->create();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => null,
    ]);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 200,
    ]);
    RecipeIngredientLine::create([
        'recipe_id' => $child->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $parent = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_quantity' => 1,
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => null,
    ]);
    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $grandparent = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 4,
        'yield_unit' => Unit::Unidad->value,
    ]);
    RecipeSubrecipeLine::create([
        'recipe_id' => $grandparent->id,
        'child_recipe_id' => $parent->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    makePropagator()->propagateFrom($child);

    // child: 200/kg
    expect((float) $child->fresh()->unit_cost)->toBe(200.0);
    // parent: 1kg child @200 / 1kg yield = 200/kg
    expect((float) $parent->fresh()->unit_cost)->toBe(200.0);
    // grandparent: 1kg parent @200 / 4 units = 50 per unit
    expect((float) $grandparent->fresh()->unit_cost)->toBe(50.0);
});

<?php

use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeSubrecipeLine;
use App\Models\Tenant;
use App\Services\RecipeCostCalculator;
use App\Services\UnitConverter;

function makeCalc(): RecipeCostCalculator
{
    return new RecipeCostCalculator(new UnitConverter);
}

test('subrecipe_cost is zero when no subrecipe lines', function () {
    $tenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1]);

    $result = makeCalc()->calculate($recipe);

    expect($result['subrecipe_cost'])->toBe(0.0);
});

test('subrecipe_cost correct with same-unit quantity', function () {
    $tenant = Tenant::factory()->create();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => 500,
    ]);
    $parent = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1]);

    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 2,
        'unit' => Unit::Kilogramo->value,
    ]);

    $result = makeCalc()->calculate($parent);

    // 2kg × 500/kg = 1000
    expect($result['subrecipe_cost'])->toBe(1000.0);
});

test('subrecipe_cost converts units correctly (gr to kg)', function () {
    $tenant = Tenant::factory()->create();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => 1000,
    ]);
    $parent = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1]);

    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 500,
        'unit' => Unit::Gramo->value,
    ]);

    $result = makeCalc()->calculate($parent);

    // 500g = 0.5kg × 1000/kg = 500
    expect($result['subrecipe_cost'])->toBe(500.0);
});

test('subrecipe_cost skips lines where child unit_cost is null', function () {
    $tenant = Tenant::factory()->create();

    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => null,
    ]);
    $parent = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1]);

    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $result = makeCalc()->calculate($parent);

    expect($result['subrecipe_cost'])->toBe(0.0);
});

test('total_cost includes subrecipe_cost as 4th term', function () {
    $tenant = Tenant::factory()->create();

    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 200,
    ]);
    $child = Recipe::factory()->for($tenant)->semiElaborate()->create([
        'yield_unit' => Unit::Kilogramo->value,
        'unit_cost' => 300,
    ]);
    $parent = Recipe::factory()->for($tenant)->create(['yield_quantity' => 1]);

    RecipeIngredientLine::create([
        'recipe_id' => $parent->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);
    RecipeSubrecipeLine::create([
        'recipe_id' => $parent->id,
        'child_recipe_id' => $child->id,
        'quantity_used' => 1,
        'unit' => Unit::Kilogramo->value,
    ]);

    $result = makeCalc()->calculate($parent);

    // ingredients: 1kg @200 = 200, subrecipe: 1kg @300 = 300, total = 500
    expect($result['ingredient_cost'])->toBe(200.0);
    expect($result['subrecipe_cost'])->toBe(300.0);
    expect($result['total_cost'])->toBe(500.0);
});

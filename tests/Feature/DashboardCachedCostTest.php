<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

// Anclas del dashboard sobre caches: lee unit_cost/labor_hours (no recalcula
// por request) y ordena/pagina en SQL.

function ownerForCachedDashboard(): array
{
    $tenant = Tenant::factory()->create(['productive_hours_month' => 160]);
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

function recipeWithCost(Tenant $tenant, string $name, float $ingredientCost, ?float $price = null): Recipe
{
    $recipe = Recipe::factory()->for($tenant)->create([
        'name' => $name,
        'yield_quantity' => 1,
        'yield_unit' => Unit::Unidad->value,
        'active' => true,
    ]);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => $ingredientCost,
    ]);
    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 1,
        'unit' => Unit::Unidad->value,
    ]);
    propagateRecipeCosts($recipe);

    if ($price !== null) {
        RecipePrice::create([
            'tenant_id' => $tenant->id,
            'recipe_id' => $recipe->id,
            'price_list_id' => $tenant->defaultPriceList()->id,
            'price' => $price,
        ]);
    }

    return $recipe;
}

test('el dashboard lee el costo del cache, no de las líneas', function () {
    [$user, $tenant] = ownerForCachedDashboard();
    $recipe = recipeWithCost($tenant, 'Pan de campo', 100);

    // Pisar el cache a mano: si el dashboard recalculara desde las líneas
    // mostraría 100, si lee el cache muestra 999.
    $recipe->updateQuietly(['unit_cost' => 999]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('999,00')
        ->assertDontSee('100,00');
});

test('ordenar por margen resuelve en SQL con el orden correcto', function () {
    [$user, $tenant] = ownerForCachedDashboard();
    recipeWithCost($tenant, 'Margen alto', 10, 100);   // margen 90
    recipeWithCost($tenant, 'Margen bajo', 90, 100);   // margen 10
    recipeWithCost($tenant, 'Sin precio', 50);          // margen null → al final

    $this->actingAs($user)
        ->get(route('dashboard', ['sort' => 'margin', 'dir' => 'desc']))
        ->assertOk()
        ->assertSeeInOrder(['Margen alto', 'Margen bajo', 'Sin precio']);

    $this->actingAs($user)
        ->get(route('dashboard', ['sort' => 'margin', 'dir' => 'asc']))
        ->assertOk()
        ->assertSeeInOrder(['Margen bajo', 'Margen alto', 'Sin precio']);
});

test('recipes:refresh-costs rellena los caches de recetas sembradas sin propagar', function () {
    [, $tenant] = ownerForCachedDashboard();

    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 2,
        'yield_unit' => Unit::Unidad->value,
    ]);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => 100,
    ]);
    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 4,
        'unit' => Unit::Unidad->value,
    ]);

    expect($recipe->unit_cost)->toBeNull();

    $this->artisan('recipes:refresh-costs')->assertSuccessful();

    $recipe->refresh();
    expect((float) $recipe->unit_cost)->toBe(200.0)   // 4 × 100 / rinde 2
        ->and((float) $recipe->labor_hours)->toBe(0.0);
});

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

// Nota: margin_filter sólo filtra la TABLA; los gráficos reflejan el catálogo
// completo (los nombres viven en el JSON del bar chart), así que se asserta
// sobre el total paginado del footer, no sobre todo el HTML.

test('margin_filter=high deja en la tabla solo recetas con margen >= 60%', function () {
    [$user, $tenant] = ownerForCachedDashboard();
    recipeWithCost($tenant, 'Muy rentable', 10, 100);   // margen 90%
    recipeWithCost($tenant, 'Poco rentable', 90, 100);  // margen 10%

    $this->actingAs($user)
        ->get(route('dashboard', ['margin_filter' => 'high']))
        ->assertOk()
        ->assertSee('Muy rentable')
        ->assertSee('de 1 recetas');   // footer: solo 1 fila tras el filtro
});

test('margin_filter=low deja en la tabla solo recetas con margen < 20% y dispara la alerta', function () {
    [$user, $tenant] = ownerForCachedDashboard();
    recipeWithCost($tenant, 'Muy rentable', 10, 100);   // margen 90%
    recipeWithCost($tenant, 'Poco rentable', 90, 100);  // margen 10%

    $this->actingAs($user)
        ->get(route('dashboard', ['margin_filter' => 'low']))
        ->assertOk()
        ->assertSee('Poco rentable')
        ->assertSee('de 1 recetas')     // footer: solo 1 fila tras el filtro
        ->assertSee('margen menor al 20%');
});

test('el dashboard renderiza los datos de los graficos cuando hay recetas con margen', function () {
    [$user, $tenant] = ownerForCachedDashboard();
    recipeWithCost($tenant, 'Pan dulce', 10, 100);   // margen 90%
    recipeWithCost($tenant, 'Factura', 30, 100);     // margen 70%

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Top recetas por rentabilidad')
        ->assertSee('Distribución de costos')
        ->assertSee('Rentabilidad promedio')
        // La dona resume la distribución de costos; con solo ingredientes cierra en 100%.
        ->assertSee('100%');
});

test('sin recetas con margen los bloques de graficos no se renderizan', function () {
    [$user, $tenant] = ownerForCachedDashboard();
    recipeWithCost($tenant, 'Sin precio', 50);   // margen null (sin precio de venta)

    // El bloque de barras + dona está gateado por $topRecipesForChart, que sin
    // margen queda vacío: no se renderiza ninguno de los dos títulos.
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Top recetas por rentabilidad')
        ->assertDontSee('Distribución de costos');
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

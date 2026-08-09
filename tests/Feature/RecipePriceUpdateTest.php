<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\PriceList;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForRecipePrice(string $role = 'owner'): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role,
        'active' => true,
    ]);

    return [$user, $tenant];
}

function recipeWithUnitCost(Tenant $tenant, float $costPerUnit): Recipe
{
    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
    ]);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => $costPerUnit,
    ]);
    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 10,
        'unit' => Unit::Unidad->value,
    ]);
    propagateRecipeCosts($recipe);

    return $recipe;
}

test('PATCH crea el precio y devuelve margen con su tramo', function () {
    [$user, $tenant] = ownerForRecipePrice();
    $recipe = recipeWithUnitCost($tenant, 50); // costo/u = 50
    $list = PriceList::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$recipe, $list]), ['price' => 100])
        ->assertOk()
        ->assertJson([
            'selling_price' => 100,
            'margin' => 50,
            'margin_pct' => 50,
            'margin_pct_formatted' => '50,0',
            'margin_tier' => 'media',
        ])
        // El color ya no viaja en la respuesta: lo deriva el cliente del tramo,
        // así los cortes tienen un solo dueño (MarginTier).
        ->assertJsonMissingPath('margin_color');

    expect((float) $recipe->prices()->where('price_list_id', $list->id)->value('price'))->toBe(100.0);
});

test('PATCH actualiza un precio existente y null lo elimina', function () {
    [$user, $tenant] = ownerForRecipePrice();
    $recipe = recipeWithUnitCost($tenant, 50);
    $list = PriceList::factory()->for($tenant)->create();
    RecipePrice::factory()->for($tenant)->create([
        'price_list_id' => $list->id,
        'recipe_id' => $recipe->id,
        'price' => 80,
    ]);

    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$recipe, $list]), ['price' => 120])
        ->assertOk()
        ->assertJson(['selling_price' => 120]);

    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$recipe, $list]), ['price' => null])
        ->assertOk()
        ->assertJson(['selling_price' => null, 'margin' => null]);

    expect($recipe->prices()->where('price_list_id', $list->id)->exists())->toBeFalse();
});

test('el semáforo del margen usa la misma escala que el dashboard', function () {
    [$user, $tenant] = ownerForRecipePrice();
    $recipe = recipeWithUnitCost($tenant, 50);
    $list = PriceList::factory()->for($tenant)->create();

    // Antes esta respuesta usaba una escala propia (30/15) distinta de la del
    // dashboard (60/40/20): editar un precio inline cambiaba el color del número
    // a un tramo que la badge de al lado contradecía. Ahora es una sola escala.

    // margen 60% → Alta
    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$recipe, $list]), ['price' => 125])
        ->assertJson(['margin_pct' => 60, 'margin_tier' => 'alta']);

    // margen 20% → Regular (con la escala vieja daba ámbar/«media»)
    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$recipe, $list]), ['price' => 62.50])
        ->assertJson(['margin_pct' => 20, 'margin_tier' => 'regular']);

    // margen 9% → Baja
    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$recipe, $list]), ['price' => 55])
        ->assertJson(['margin_tier' => 'baja']);
});

test('loguea al crear y al cambiar, no si el monto no cambió', function () {
    [$user, $tenant] = ownerForRecipePrice();
    $recipe = recipeWithUnitCost($tenant, 50);
    $list = PriceList::factory()->for($tenant)->create();
    $url = route('recipes.prices.update', [$recipe, $list]);

    $this->actingAs($user)->patchJson($url, ['price' => 100]);
    $this->actingAs($user)->patchJson($url, ['price' => 100]);
    expect($recipe->priceLogs()->count())->toBe(1);

    $this->actingAs($user)->patchJson($url, ['price' => 110]);
    expect($recipe->priceLogs()->count())->toBe(2);
});

test('viewer no puede setear precios', function () {
    [$user, $tenant] = ownerForRecipePrice(TenantUserRole::Viewer->value);
    $recipe = Recipe::factory()->for($tenant)->create();
    $list = PriceList::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$recipe, $list]), ['price' => 100])
        ->assertForbidden();
});

test('aislamiento: receta o lista de otro tenant devuelve 403', function () {
    [$user, $tenant] = ownerForRecipePrice();
    $other = Tenant::factory()->create();

    $foreignRecipe = Recipe::factory()->for($other)->create();
    $ownList = PriceList::factory()->for($tenant)->create();
    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$foreignRecipe, $ownList]), ['price' => 100])
        ->assertNotFound();

    $ownRecipe = Recipe::factory()->for($tenant)->create();
    $foreignList = PriceList::factory()->for($other)->create();
    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$ownRecipe, $foreignList]), ['price' => 100])
        ->assertNotFound();
});

test('una lista inactiva devuelve 422', function () {
    [$user, $tenant] = ownerForRecipePrice();
    $recipe = Recipe::factory()->for($tenant)->create();
    $list = PriceList::factory()->for($tenant)->create(['active' => false]);

    $this->actingAs($user)
        ->patchJson(route('recipes.prices.update', [$recipe, $list]), ['price' => 100])
        ->assertStatus(422);
});

test('crear receta con selling_price escribe en la lista default', function () {
    [$user, $tenant] = ownerForRecipePrice();

    $this->actingAs($user)->post(route('recipes.store'), [
        'name' => 'Pan de campo',
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
        'selling_price' => 350,
    ]);

    $recipe = $tenant->recipes()->where('name', 'Pan de campo')->first();
    $defaultList = $tenant->defaultPriceList();

    expect((float) $recipe->prices()->where('price_list_id', $defaultList->id)->value('price'))->toBe(350.0);
});

test('editar receta actualiza el precio de la lista default y vacío lo borra', function () {
    [$user, $tenant] = ownerForRecipePrice();
    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
    ]);
    $defaultList = $tenant->defaultPriceList();

    $this->actingAs($user)->put(route('recipes.update', $recipe), [
        'name' => $recipe->name,
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
        'selling_price' => 500,
    ]);
    expect((float) $recipe->prices()->where('price_list_id', $defaultList->id)->value('price'))->toBe(500.0);

    $this->actingAs($user)->put(route('recipes.update', $recipe), [
        'name' => $recipe->name,
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
        'selling_price' => null,
    ]);
    expect($recipe->prices()->where('price_list_id', $defaultList->id)->exists())->toBeFalse();
});

test('copiar una receta copia sus precios en todas las listas', function () {
    [$user, $tenant] = ownerForRecipePrice();
    $recipe = Recipe::factory()->for($tenant)->create();
    $defaultList = $tenant->defaultPriceList();
    $otherList = PriceList::factory()->for($tenant)->create();
    RecipePrice::factory()->for($tenant)->create([
        'price_list_id' => $defaultList->id,
        'recipe_id' => $recipe->id,
        'price' => 300,
    ]);
    RecipePrice::factory()->for($tenant)->create([
        'price_list_id' => $otherList->id,
        'recipe_id' => $recipe->id,
        'price' => 250,
    ]);

    $this->actingAs($user)->post(route('recipes.copy', $recipe));

    $copy = $tenant->recipes()->where('name', $recipe->name.' (copia)')->first();
    expect($copy)->not->toBeNull()
        ->and((float) $copy->prices()->where('price_list_id', $defaultList->id)->value('price'))->toBe(300.0)
        ->and((float) $copy->prices()->where('price_list_id', $otherList->id)->value('price'))->toBe(250.0);
});

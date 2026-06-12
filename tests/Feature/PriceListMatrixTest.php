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

function userForMatrix(string $role = 'owner'): array
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

test('la matriz muestra una columna por lista activa y el costo por unidad', function () {
    [$user, $tenant] = userForMatrix();

    $recipe = Recipe::factory()->for($tenant)->create([
        'name' => 'Pan flauta',
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
    ]);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad->value,
        'cost_per_unit' => 50,
    ]);
    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 10,
        'unit' => Unit::Unidad->value,
    ]);

    PriceList::factory()->for($tenant)->create(['name' => 'Mayorista']);
    PriceList::factory()->for($tenant)->create(['name' => 'ListaInactiva', 'active' => false]);

    $this->actingAs($user)
        ->get(route('price-lists.matrix'))
        ->assertOk()
        ->assertSee('Pan flauta')
        ->assertSee('General')
        ->assertSee('Mayorista')
        ->assertDontSee('ListaInactiva')
        ->assertSee('50,00'); // costo/u
});

test('una celda vacía muestra la sugerencia calculada con el % de la lista', function () {
    [$user, $tenant] = userForMatrix();

    $recipe = Recipe::factory()->for($tenant)->create();
    RecipePrice::factory()->for($tenant)->create([
        'price_list_id' => $tenant->defaultPriceList()->id,
        'recipe_id' => $recipe->id,
        'price' => 1000,
    ]);
    PriceList::factory()->for($tenant)->create(['name' => 'Mayorista', 'adjustment_pct' => -15]);

    $this->actingAs($user)
        ->get(route('price-lists.matrix'))
        ->assertOk()
        ->assertSee('850,00'); // 1000 - 15%
});

test('sin precio base o sin % no hay sugerencia', function () {
    [$user, $tenant] = userForMatrix();

    Recipe::factory()->for($tenant)->create(['name' => 'Sin precio']);
    PriceList::factory()->for($tenant)->create(['name' => 'Mayorista', 'adjustment_pct' => -15]);
    PriceList::factory()->for($tenant)->create(['name' => 'Cafeterías', 'adjustment_pct' => null]);

    $response = $this->actingAs($user)
        ->get(route('price-lists.matrix'))
        ->assertOk();

    expect($response->getContent())->not->toMatch('/suggested: \d/');
});

test('las recetas semi-elaboradas no aparecen en la matriz', function () {
    [$user, $tenant] = userForMatrix();

    Recipe::factory()->for($tenant)->create(['name' => 'Pan de venta']);
    Recipe::factory()->for($tenant)->semiElaborate()->create(['name' => 'Masa madre interna']);

    $this->actingAs($user)
        ->get(route('price-lists.matrix'))
        ->assertOk()
        ->assertSee('Pan de venta')
        ->assertDontSee('Masa madre interna');
});

test('la búsqueda filtra recetas por nombre', function () {
    [$user, $tenant] = userForMatrix();

    Recipe::factory()->for($tenant)->create(['name' => 'Medialunas']);
    Recipe::factory()->for($tenant)->create(['name' => 'Chipá']);

    $this->actingAs($user)
        ->get(route('price-lists.matrix', ['search' => 'Media']))
        ->assertOk()
        ->assertSee('Medialunas')
        ->assertDontSee('Chipá');
});

test('viewer accede a la matriz en solo lectura', function () {
    [$user, $tenant] = userForMatrix(TenantUserRole::Viewer->value);

    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Pan lactal']);
    RecipePrice::factory()->for($tenant)->create([
        'price_list_id' => $tenant->defaultPriceList()->id,
        'recipe_id' => $recipe->id,
        'price' => 700,
    ]);

    $this->actingAs($user)
        ->get(route('price-lists.matrix'))
        ->assertOk()
        ->assertSee('Pan lactal')
        ->assertSee('700,00')
        ->assertDontSee('savePrice');
});

test('aislamiento: recetas y listas de otro tenant no aparecen', function () {
    [$user] = userForMatrix();
    $other = Tenant::factory()->create();
    Recipe::factory()->for($other)->create(['name' => 'RecetaAjena']);
    PriceList::factory()->for($other)->create(['name' => 'ListaAjena']);

    $this->actingAs($user)
        ->get(route('price-lists.matrix'))
        ->assertOk()
        ->assertDontSee('RecetaAjena')
        ->assertDontSee('ListaAjena');
});

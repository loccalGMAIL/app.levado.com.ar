<?php

use App\Enums\ProductType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\ProductPriceWriter;

/**
 * La matriz vive dentro de Artículos (products.matrix) y es product-céntrica:
 * filas = artículos (elaborados y de reventa), columnas = listas de precios.
 */
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

/** Crea el artículo elaborado de una receta con su precio en una lista (helper local). */
function matrixArticle(Recipe $recipe, PriceList $list, float $price): Product
{
    $product = Product::factory()->create([
        'tenant_id' => $recipe->tenant_id,
        'name' => $recipe->name,
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => $recipe->yield_unit->value,
    ]);
    app(ProductPriceWriter::class)->set($product, $list, $price);

    return $product;
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
    propagateRecipeCosts($recipe);
    matrixArticle($recipe, $tenant->defaultPriceList(), 1000); // crea el artículo elaborado

    PriceList::factory()->for($tenant)->create(['name' => 'Mayorista']);
    PriceList::factory()->for($tenant)->create(['name' => 'ListaInactiva', 'active' => false]);

    $this->actingAs($user)
        ->get(route('products.matrix'))
        ->assertOk()
        ->assertSee('Pan flauta')
        ->assertSee('General')
        ->assertSee('Mayorista')
        ->assertDontSee('ListaInactiva')
        ->assertSee('50,00'); // costo/u
});

test('un artículo de reventa aparece en la matriz', function () {
    [$user, $tenant] = userForMatrix();

    Product::factory()->for($tenant)->create([
        'name' => 'Gaseosa 500ml',
        'type' => ProductType::Resale->value,
        'recipe_id' => null,
        'cost_per_unit' => 300,
    ]);

    $this->actingAs($user)
        ->get(route('products.matrix'))
        ->assertOk()
        ->assertSee('Gaseosa 500ml');
});

test('una celda vacía muestra la sugerencia calculada con el % de la lista', function () {
    [$user, $tenant] = userForMatrix();

    $recipe = Recipe::factory()->for($tenant)->create();
    matrixArticle($recipe, $tenant->defaultPriceList(), 1000);
    PriceList::factory()->for($tenant)->create(['name' => 'Mayorista', 'adjustment_pct' => -15]);

    $this->actingAs($user)
        ->get(route('products.matrix'))
        ->assertOk()
        ->assertSee('850,00'); // 1000 - 15%
});

test('sin precio base o sin % no hay sugerencia', function () {
    [$user, $tenant] = userForMatrix();

    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Sin precio']);
    Product::factory()->for($tenant)->create([
        'type' => ProductType::Manufactured->value,
        'recipe_id' => $recipe->id,
        'cost_per_unit' => null,
        'unit' => Unit::Unidad->value,
    ]);
    PriceList::factory()->for($tenant)->create(['name' => 'Mayorista', 'adjustment_pct' => -15]);

    $response = $this->actingAs($user)
        ->get(route('products.matrix'))
        ->assertOk();

    expect($response->getContent())->not->toMatch('/suggested: \d/');
});

test('la búsqueda filtra artículos por nombre', function () {
    [$user, $tenant] = userForMatrix();

    matrixArticle(Recipe::factory()->for($tenant)->create(['name' => 'Medialunas']), $tenant->defaultPriceList(), 100);
    matrixArticle(Recipe::factory()->for($tenant)->create(['name' => 'Chipá']), $tenant->defaultPriceList(), 100);

    $this->actingAs($user)
        ->get(route('products.matrix', ['search' => 'Media']))
        ->assertOk()
        ->assertSee('Medialunas')
        ->assertDontSee('Chipá');
});

test('viewer accede a la matriz en solo lectura', function () {
    [$user, $tenant] = userForMatrix(TenantUserRole::Viewer->value);

    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Pan lactal']);
    matrixArticle($recipe, $tenant->defaultPriceList(), 700);

    $this->actingAs($user)
        ->get(route('products.matrix'))
        ->assertOk()
        ->assertSee('Pan lactal')
        ->assertSee('700,00')
        ->assertDontSee('priceCell('); // sin editor para el viewer
});

test('aislamiento: artículos y listas de otro tenant no aparecen', function () {
    [$user] = userForMatrix();
    $other = Tenant::factory()->create();
    matrixArticle(Recipe::factory()->for($other)->create(['name' => 'RecetaAjena']), $other->defaultPriceList(), 500);
    PriceList::factory()->for($other)->create(['name' => 'ListaAjena']);

    $this->actingAs($user)
        ->get(route('products.matrix'))
        ->assertOk()
        ->assertDontSee('RecetaAjena')
        ->assertDontSee('ListaAjena');
});

<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\LaborType;
use App\Models\Packaging;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function recipePrintUser(TenantUserRole $role): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

function recipeToPrint(Tenant $tenant): Recipe
{
    $recipe = Recipe::factory()->for($tenant)->create([
        'name' => 'Medialunas de manteca',
        'description' => null,
        'yield_quantity' => 12,
        'yield_unit' => Unit::Unidad->value,
    ]);

    $harina = Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000', 'unit' => Unit::Gramo->value, 'cost_per_unit' => 7.77]);
    $recipe->ingredientLines()->create(['ingredient_id' => $harina->id, 'quantity' => 500, 'unit' => Unit::Gramo->value]);

    return $recipe;
}

test('la receta imprimible muestra el encabezado del comercio y los ingredientes sin costos', function () {
    [$user, $tenant] = recipePrintUser(TenantUserRole::Owner);
    $tenant->update(['razon_social' => 'Panadería Levado S.R.L.']);
    $recipe = recipeToPrint($tenant);

    $this->actingAs($user)
        ->get(route('recipes.print', $recipe))
        ->assertOk()
        ->assertSeeInOrder(['Panadería Levado S.R.L.', 'Medialunas de manteca', 'Ingredientes', 'Harina 000', '500,000'])
        ->assertDontSee('7,77')
        ->assertDontSee('Costo total')
        ->assertDontSee('Precio de venta');
});

test('la receta imprimible incluye envases y mano de obra', function () {
    [$user, $tenant] = recipePrintUser(TenantUserRole::Owner);
    $recipe = recipeToPrint($tenant);

    $caja = Packaging::factory()->for($tenant)->create(['name' => 'Caja chica']);
    $recipe->packagingLines()->create(['packaging_id' => $caja->id, 'quantity' => 2]);
    $oficial = LaborType::factory()->for($tenant)->create(['name' => 'Oficial panadero']);
    $recipe->laborLines()->create(['labor_type_id' => $oficial->id, 'hours' => 1.5]);

    $this->actingAs($user)
        ->get(route('recipes.print', $recipe))
        ->assertOk()
        ->assertSeeInOrder(['Envases', 'Caja chica', 'Mano de obra', 'Oficial panadero', '1,50 h']);
});

test('el PDF de la receta se descarga', function () {
    [$user, $tenant] = recipePrintUser(TenantUserRole::Owner);
    $recipe = recipeToPrint($tenant);

    $this->actingAs($user)
        ->get(route('recipes.print.pdf', $recipe))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('el listado de recetas tiene un botón de impresión por receta', function () {
    [$user, $tenant] = recipePrintUser(TenantUserRole::Viewer);
    $recipe = recipeToPrint($tenant);

    $this->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk()
        ->assertSee(route('recipes.print', $recipe), false);
});

test('viewer puede ver la receta imprimible', function () {
    [$user, $tenant] = recipePrintUser(TenantUserRole::Viewer);
    $recipe = recipeToPrint($tenant);

    $this->actingAs($user)->get(route('recipes.print', $recipe))->assertOk();
});

test('aislamiento: no se puede imprimir la receta de otro tenant', function () {
    [$user] = recipePrintUser(TenantUserRole::Owner);
    $otherRecipe = recipeToPrint(Tenant::factory()->create());

    $this->actingAs($user)->get(route('recipes.print', $otherRecipe))->assertNotFound();
});

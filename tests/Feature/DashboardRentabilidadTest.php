<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\FixedCost;
use App\Models\FixedCostCategory;
use App\Models\Ingredient;
use App\Models\PriceList;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForDashboard(): array
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

test('dashboard carga correctamente para un owner', function () {
    [$user] = ownerForDashboard();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('dashboard muestra las recetas activas con su costo', function () {
    [$user, $tenant] = ownerForDashboard();

    $recipe = Recipe::factory()->for($tenant)->create([
        'name' => 'Medialunas',
        'yield_quantity' => 12,
        'yield_unit' => Unit::Unidad->value,
        'active' => true,
    ]);

    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo->value,
        'cost_per_unit' => 1200,
    ]);
    RecipeIngredientLine::create([
        'recipe_id' => $recipe->id,
        'ingredient_id' => $ingredient->id,
        'quantity' => 500,
        'unit' => Unit::Gramo->value,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Medialunas')
        ->assertSee('600,00');
});

test('dashboard muestra la badge semi para recetas semielaboradas', function () {
    [$user, $tenant] = ownerForDashboard();

    Recipe::factory()->for($tenant)->create([
        'name' => 'Masa madre base',
        'is_semi_elaborate' => true,
    ]);
    Recipe::factory()->for($tenant)->create([
        'name' => 'Pan lactal',
        'is_semi_elaborate' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Masa madre base', 'semi'])
        ->assertSeeInOrder(['Pan lactal']);
});

test('dashboard muestra el margen cuando hay precio de venta', function () {
    [$user, $tenant] = ownerForDashboard();

    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
    ]);
    RecipePrice::factory()->for($tenant)->create([
        'price_list_id' => $tenant->defaultPriceList()->id,
        'recipe_id' => $recipe->id,
        'price' => 100,
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

    // total cost = 500, cost/u = 50, selling = 100, margin = 50, margin% = 50%
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('50,00')
        ->assertSee('50,0');
});

test('dashboard muestra los gastos fijos activos', function () {
    [$user, $tenant] = ownerForDashboard();

    $category = FixedCostCategory::create(['tenant_id' => $tenant->id, 'name' => 'Servicios']);
    FixedCost::factory()->for($tenant)->create([
        'monthly_amount' => 5000,
        'active' => true,
        'fixed_cost_category_id' => $category->id,
    ]);
    FixedCost::factory()->for($tenant)->create([
        'monthly_amount' => 3000,
        'active' => true,
        'fixed_cost_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('8.000,00');
});

test('dashboard muestra overhead por hora cuando hay horas productivas', function () {
    [$user, $tenant] = ownerForDashboard();

    $category = FixedCostCategory::create(['tenant_id' => $tenant->id, 'name' => 'Infra']);
    FixedCost::factory()->for($tenant)->create([
        'monthly_amount' => 16000,
        'active' => true,
        'fixed_cost_category_id' => $category->id,
    ]);

    // tenant has 160 productive hours, 16000 / 160 = 100/h
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('100,00');
});

test('el selector de lista muestra los márgenes de la lista elegida', function () {
    [$user, $tenant] = ownerForDashboard();

    $recipe = Recipe::factory()->for($tenant)->create([
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

    $mayorista = PriceList::factory()->for($tenant)->create(['name' => 'Mayorista']);
    RecipePrice::factory()->for($tenant)->create([
        'price_list_id' => $tenant->defaultPriceList()->id,
        'recipe_id' => $recipe->id,
        'price' => 100,
    ]);
    RecipePrice::factory()->for($tenant)->create([
        'price_list_id' => $mayorista->id,
        'recipe_id' => $recipe->id,
        'price' => 80,
    ]);

    // costo/u = 50; en Mayorista: margen 30, margen% 37,5
    $this->actingAs($user)
        ->get(route('dashboard', ['price_list' => $mayorista->id]))
        ->assertOk()
        ->assertSee('Precio (Mayorista) / u')
        ->assertSee('37,5');
});

test('un price_list inválido o de otro tenant cae a la lista default', function () {
    [$user, $tenant] = ownerForDashboard();
    Recipe::factory()->for($tenant)->create();
    $other = Tenant::factory()->create();
    $foreignList = PriceList::factory()->for($other)->create(['name' => 'Ajena']);

    $this->actingAs($user)
        ->get(route('dashboard', ['price_list' => $foreignList->id]))
        ->assertOk()
        ->assertSee('Precio (General) / u');

    $this->actingAs($user)
        ->get(route('dashboard', ['price_list' => 99999]))
        ->assertOk()
        ->assertSee('Precio (General) / u');
});

test('una lista inactiva no se puede seleccionar en el dashboard', function () {
    [$user, $tenant] = ownerForDashboard();
    Recipe::factory()->for($tenant)->create();
    $inactive = PriceList::factory()->for($tenant)->create(['name' => 'Vieja', 'active' => false]);

    $this->actingAs($user)
        ->get(route('dashboard', ['price_list' => $inactive->id]))
        ->assertOk()
        ->assertSee('Precio (General) / u');
});

test('aislamiento: recetas de otro tenant no aparecen en el dashboard', function () {
    [$user] = ownerForDashboard();

    $other = Tenant::factory()->create();
    Recipe::factory()->for($other)->create(['name' => 'RecetaAjena']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('RecetaAjena');
});

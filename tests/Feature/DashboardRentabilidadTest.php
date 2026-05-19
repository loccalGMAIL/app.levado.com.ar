<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\FixedCost;
use App\Models\FixedCostCategory;
use App\Models\Ingredient;
use App\Models\LaborType;
use App\Models\Packaging;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeLaborLine;
use App\Models\RecipePackagingLine;
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

test('dashboard muestra el margen cuando hay precio de venta', function () {
    [$user, $tenant] = ownerForDashboard();

    $recipe = Recipe::factory()->for($tenant)->create([
        'yield_quantity' => 10,
        'yield_unit' => Unit::Unidad->value,
        'selling_price' => 100,
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

test('aislamiento: recetas de otro tenant no aparecen en el dashboard', function () {
    [$user] = ownerForDashboard();

    $other = Tenant::factory()->create();
    Recipe::factory()->for($other)->create(['name' => 'RecetaAjena']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('RecetaAjena');
});

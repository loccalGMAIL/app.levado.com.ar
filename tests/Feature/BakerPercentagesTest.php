<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\BakerPercentages;

function bakerUser(TenantUserRole $role, ?Tenant $tenant = null): array
{
    $tenant ??= Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

function bakerLine(Recipe $recipe, string $name, float $quantity, Unit $unit, bool $flour = false, array $ingredient = [])
{
    $ing = Ingredient::factory()->for($recipe->tenant)->create(['name' => $name, 'unit' => $unit->value] + $ingredient);

    return $recipe->ingredientLines()->create([
        'ingredient_id' => $ing->id,
        'quantity' => $quantity,
        'unit' => $unit->value,
        'is_flour' => $flour,
    ]);
}

function bakerRow(array $result, string $name): array
{
    return collect($result['rows'])->firstWhere('name', $name);
}

test('una harina marcada es el 100% y el resto se calcula sobre ella', function () {
    $recipe = Recipe::factory()->for(Tenant::factory()->create())->create();
    bakerLine($recipe, 'Harina 000', 1000, Unit::Gramo, true);
    bakerLine($recipe, 'Agua', 700, Unit::Gramo);
    bakerLine($recipe, 'Sal', 20, Unit::Gramo);

    $result = app(BakerPercentages::class)->for($recipe);

    expect($result['has_flour'])->toBeTrue()
        ->and($result['flour_grams'])->toBe(1000.0)
        ->and($result['dough_grams'])->toBe(1720.0)
        ->and($result['total_percent'])->toBe(172.0)
        ->and(bakerRow($result, 'Harina 000')['percent'])->toBe(100.0)
        ->and(bakerRow($result, 'Agua')['percent'])->toBe(70.0)
        ->and(bakerRow($result, 'Sal')['percent'])->toBe(2.0);
});

test('varias harinas suman la base', function () {
    $recipe = Recipe::factory()->for(Tenant::factory()->create())->create();
    bakerLine($recipe, 'Harina 000', 800, Unit::Gramo, true);
    bakerLine($recipe, 'Integral', 200, Unit::Gramo, true);
    bakerLine($recipe, 'Agua', 650, Unit::Gramo);

    $result = app(BakerPercentages::class)->for($recipe);

    expect($result['flour_grams'])->toBe(1000.0)
        ->and(bakerRow($result, 'Integral')['percent'])->toBe(20.0)
        ->and(bakerRow($result, 'Agua')['percent'])->toBe(65.0);
});

test('kg se convierte a gramos y ml cuenta como gramo', function () {
    $recipe = Recipe::factory()->for(Tenant::factory()->create())->create();
    bakerLine($recipe, 'Harina 000', 1, Unit::Kilogramo, true);
    bakerLine($recipe, 'Leche', 500, Unit::Mililitro);
    bakerLine($recipe, 'Aceite', 0.1, Unit::Litro);

    $result = app(BakerPercentages::class)->for($recipe);

    expect(bakerRow($result, 'Harina 000')['grams'])->toBe(1000.0)
        ->and(bakerRow($result, 'Leche')['percent'])->toBe(50.0)
        ->and(bakerRow($result, 'Aceite')['percent'])->toEqualWithDelta(10.0, 0.0001);
});

test('las unidades y los insumos con subdivisiones no entran en la masa', function () {
    $recipe = Recipe::factory()->for(Tenant::factory()->create())->create();
    bakerLine($recipe, 'Harina 000', 1000, Unit::Gramo, true);
    bakerLine($recipe, 'Huevo', 3, Unit::Unidad);
    bakerLine($recipe, 'Levadura', 2, Unit::Gramo, false, ['subdivisions' => 10, 'subdivision_label' => 'sobre']);

    $result = app(BakerPercentages::class)->for($recipe);

    expect(bakerRow($result, 'Huevo')['grams'])->toBeNull()
        ->and(bakerRow($result, 'Huevo')['percent'])->toBeNull()
        ->and(bakerRow($result, 'Levadura')['grams'])->toBeNull()
        ->and($result['dough_grams'])->toBe(1000.0);
});

test('una sub-receta se cuenta como una línea más con su porcentaje', function () {
    $tenant = Tenant::factory()->create();
    $recipe = Recipe::factory()->for($tenant)->create();
    bakerLine($recipe, 'Harina 000', 1000, Unit::Gramo, true);
    $masaMadre = Recipe::factory()->for($tenant)->create(['name' => 'Masa madre', 'is_semi_elaborate' => true]);
    $recipe->subrecipeLines()->create(['child_recipe_id' => $masaMadre->id, 'quantity_used' => 200, 'unit' => Unit::Gramo->value]);

    $result = app(BakerPercentages::class)->for($recipe);
    $row = bakerRow($result, 'Masa madre');

    expect($row['kind'])->toBe('subrecipe')
        ->and($row['is_flour'])->toBeFalse()
        ->and($row['percent'])->toBe(20.0);
});

test('sin harina marcada no hay porcentajes', function () {
    $recipe = Recipe::factory()->for(Tenant::factory()->create())->create();
    bakerLine($recipe, 'Harina 000', 1000, Unit::Gramo);

    $result = app(BakerPercentages::class)->for($recipe);

    expect($result['has_flour'])->toBeFalse()
        ->and($result['scale'])->toBe(1.0)
        ->and(bakerRow($result, 'Harina 000')['percent'])->toBeNull();
});

test('escalar a una cantidad de harina multiplica las cantidades', function () {
    $recipe = Recipe::factory()->for(Tenant::factory()->create())->create();
    bakerLine($recipe, 'Harina 000', 1000, Unit::Gramo, true);
    bakerLine($recipe, 'Agua', 700, Unit::Gramo);

    $result = app(BakerPercentages::class)->for($recipe, 5000);

    expect($result['scale'])->toBe(5.0)
        ->and(bakerRow($result, 'Agua')['scaled_quantity'])->toBe(3500.0)
        ->and(bakerRow($result, 'Agua')['percent'])->toBe(70.0);
});

test('el dueño marca una línea como harina y recibe los datos recalculados', function () {
    [$user, $tenant] = bakerUser(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create();
    $line = bakerLine($recipe, 'Harina 000', 1000, Unit::Gramo);

    $this->actingAs($user)
        ->patchJson(route('recipes.ingredient-lines.flour', [$recipe, $line]), ['is_flour' => true])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('has_flour', true)
        ->assertJsonPath('flour_grams', 1000);

    expect($line->fresh()->is_flour)->toBeTrue();
});

test('un rol sin permiso de edición no puede marcar harinas', function () {
    [$user, $tenant] = bakerUser(TenantUserRole::Viewer);
    $recipe = Recipe::factory()->for($tenant)->create();
    $line = bakerLine($recipe, 'Harina 000', 1000, Unit::Gramo);

    $this->actingAs($user)
        ->patchJson(route('recipes.ingredient-lines.flour', [$recipe, $line]), ['is_flour' => true])
        ->assertForbidden();

    expect($line->fresh()->is_flour)->toBeFalse();
});

test('una línea de otra receta resuelve 404', function () {
    [$user, $tenant] = bakerUser(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create();
    $other = Recipe::factory()->for($tenant)->create();
    $line = bakerLine($other, 'Harina 000', 1000, Unit::Gramo);

    $this->actingAs($user)
        ->patchJson(route('recipes.ingredient-lines.flour', [$recipe, $line]), ['is_flour' => true])
        ->assertNotFound();
});

test('la vista de % panadero muestra los porcentajes', function () {
    [$user, $tenant] = bakerUser(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create(['name' => 'Pan de campo']);
    bakerLine($recipe, 'Harina 000', 1000, Unit::Gramo, true);
    bakerLine($recipe, 'Agua', 700, Unit::Gramo);

    $this->actingAs($user)
        ->get(route('recipes.baker', $recipe))
        ->assertOk()
        ->assertSee('Pan de campo')
        ->assertSee('Agua')
        ->assertSee('\\'.'u0022grams\\'.'u0022:700,', false);
});

test('la vista de % panadero de otro negocio está bloqueada', function () {
    [$user] = bakerUser(TenantUserRole::Owner);
    $other = Recipe::factory()->for(Tenant::factory()->create())->create();

    $this->actingAs($user)->get(route('recipes.baker', $other))->assertNotFound();
});

test('copiar una receta conserva la marca de harina', function () {
    [$user, $tenant] = bakerUser(TenantUserRole::Owner);
    $recipe = Recipe::factory()->for($tenant)->create();
    bakerLine($recipe, 'Harina 000', 1000, Unit::Gramo, true);

    $this->actingAs($user)->post(route('recipes.copy', $recipe))->assertRedirect();

    $copy = Recipe::where('name', $recipe->name.' (copia)')->firstOrFail();
    expect($copy->ingredientLines()->first()->is_flour)->toBeTrue();
});

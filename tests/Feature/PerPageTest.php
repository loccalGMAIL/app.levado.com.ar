<?php

use App\Enums\TenantUserRole;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function perPageUser(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

test('el listado pagina de a 20 por defecto y muestra el selector', function () {
    [$user, $tenant] = perPageUser();
    Recipe::factory()->count(25)->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk()
        ->assertViewHas('recipes', fn ($recipes) => $recipes->perPage() === 20 && $recipes->total() === 25)
        ->assertSee('Filas por página')
        ->assertSee('new window.URL(', false);
});

test('per_page elige la cantidad de filas', function () {
    [$user, $tenant] = perPageUser();
    Recipe::factory()->count(25)->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('recipes.index', ['per_page' => 50]))
        ->assertOk()
        ->assertViewHas('recipes', fn ($recipes) => $recipes->perPage() === 50 && $recipes->count() === 25);
});

test('un per_page fuera de las opciones se ignora', function () {
    [$user, $tenant] = perPageUser();
    Recipe::factory()->count(25)->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('recipes.index', ['per_page' => 7]))
        ->assertViewHas('recipes', fn ($recipes) => $recipes->perPage() === 20);
});

test('la cantidad elegida se recuerda por listado', function () {
    [$user, $tenant] = perPageUser();
    Recipe::factory()->count(25)->for($tenant)->create();
    Ingredient::factory()->count(25)->for($tenant)->create();

    $this->actingAs($user)->get(route('recipes.index', ['per_page' => 100]));

    $this->get(route('recipes.index'))
        ->assertViewHas('recipes', fn ($recipes) => $recipes->perPage() === 100);

    $this->get(route('ingredients.index'))
        ->assertViewHas('ingredients', fn ($ingredients) => $ingredients->perPage() === 20);
});

test('con 20 filas o menos no se muestra el selector', function () {
    [$user, $tenant] = perPageUser();
    Recipe::factory()->count(5)->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('recipes.index'))
        ->assertOk()
        ->assertDontSee('Filas por página');
});

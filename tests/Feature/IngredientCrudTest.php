<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function tenantUserAs(TenantUserRole $role): array
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

// --- Listado ---

test('owner puede ver la lista de ingredientes', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    Ingredient::factory()->for($tenant)->create(['name' => 'Harina 000']);

    $this->actingAs($user)
        ->get(route('ingredients.index'))
        ->assertOk()
        ->assertSee('Harina 000');
});

test('viewer puede ver la lista de ingredientes', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Viewer);
    Ingredient::factory()->for($tenant)->create(['name' => 'Levadura']);

    $this->actingAs($user)
        ->get(route('ingredients.index'))
        ->assertOk()
        ->assertSee('Levadura');
});

// --- Crear ---

test('owner puede crear un ingrediente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('ingredients.store'), [
            'name' => 'Manteca',
            'unit' => Unit::Kilogramo->value,
            'cost_per_unit' => '1500.50',
        ])
        ->assertRedirect(route('ingredients.index'));

    expect($tenant->ingredients()->where('name', 'Manteca')->exists())->toBeTrue();
});

test('admin puede crear un ingrediente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Admin);

    $this->actingAs($user)
        ->post(route('ingredients.store'), [
            'name' => 'Azúcar',
            'unit' => Unit::Kilogramo->value,
            'cost_per_unit' => '800',
        ])
        ->assertRedirect(route('ingredients.index'));

    expect($tenant->ingredients()->where('name', 'Azúcar')->exists())->toBeTrue();
});

test('viewer no puede crear ingredientes', function () {
    [$user] = tenantUserAs(TenantUserRole::Viewer);

    $this->actingAs($user)
        ->post(route('ingredients.store'), [
            'name' => 'Sal',
            'unit' => Unit::Kilogramo->value,
            'cost_per_unit' => '100',
        ])
        ->assertForbidden();
});

test('nombre es obligatorio', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('ingredients.store'), [
            'unit' => Unit::Kilogramo->value,
            'cost_per_unit' => '100',
        ])
        ->assertSessionHasErrors('name');
});

test('unidad inválida es rechazada', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('ingredients.store'), [
            'name' => 'Test',
            'unit' => 'tonelada',
            'cost_per_unit' => '100',
        ])
        ->assertSessionHasErrors('unit');
});

// --- Editar ---

test('owner puede editar un ingrediente propio', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Original']);

    $this->actingAs($user)
        ->put(route('ingredients.update', $ingredient), [
            'name' => 'Actualizado',
            'unit' => Unit::Gramo->value,
            'cost_per_unit' => '5',
        ])
        ->assertRedirect(route('ingredients.index'));

    expect($ingredient->fresh()->name)->toBe('Actualizado');
});

test('editar un ingrediente vuelve a la misma página y filtros del listado', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['name' => 'Original']);

    $listadoConFiltros = route('ingredients.index').'?search=harina&status=active&sort=name&dir=asc&page=2';

    $this->actingAs($user)
        ->from($listadoConFiltros)
        ->put(route('ingredients.update', $ingredient), [
            'name' => 'Actualizado',
            'unit' => Unit::Gramo->value,
            'cost_per_unit' => '5',
        ])
        ->assertRedirect($listadoConFiltros);
});

// --- Toggle ---

test('owner puede desactivar un ingrediente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('ingredients.toggle-active', $ingredient))
        ->assertRedirect();

    expect($ingredient->fresh()->active)->toBeFalse();
});

// --- Aislamiento ---

test('aislamiento: ingredientes de otro tenant no aparecen en el listado', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $otherTenant = Tenant::factory()->create();
    Ingredient::factory()->for($otherTenant)->create(['name' => 'Ajeno']);

    $this->actingAs($user)
        ->get(route('ingredients.index'))
        ->assertDontSee('Ajeno');
});

test('aislamiento: no se puede editar ingrediente de otro tenant', function () {
    [$user] = tenantUserAs(TenantUserRole::Owner);

    $otherTenant = Tenant::factory()->create();
    $other = Ingredient::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->put(route('ingredients.update', $other), [
            'name' => 'Hack',
            'unit' => Unit::Gramo->value,
            'cost_per_unit' => '1',
        ])
        ->assertForbidden();
});

// --- Subdivisiones ---

test('crear ingrediente con subdivisión divide el costo y guarda precio por envase', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('ingredients.store'), [
            'name' => 'Galletas Oreo',
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '240',
            'subdivisions' => '24',
            'subdivision_label' => 'galleta',
        ])
        ->assertRedirect(route('ingredients.index'));

    $ingredient = $tenant->ingredients()->where('name', 'Galletas Oreo')->first();

    expect((float) $ingredient->cost_per_package)->toBe(240.0)
        ->and((float) $ingredient->cost_per_unit)->toBe(10.0);
});

test('crear ingrediente sin subdivisión no setea cost_per_package', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);

    $this->actingAs($user)
        ->post(route('ingredients.store'), [
            'name' => 'Harina',
            'unit' => Unit::Kilogramo->value,
            'cost_per_unit' => '1500',
        ])
        ->assertRedirect(route('ingredients.index'));

    $ingredient = $tenant->ingredients()->where('name', 'Harina')->first();

    expect($ingredient->cost_per_package)->toBeNull()
        ->and((float) $ingredient->cost_per_unit)->toBe(1500.0);
});

test('editar ingrediente con subdivisión divide el costo correctamente', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad,
        'cost_per_unit' => '10',
        'cost_per_package' => '240',
        'subdivisions' => 24,
        'subdivision_label' => 'galleta',
    ]);

    $this->actingAs($user)
        ->put(route('ingredients.update', $ingredient), [
            'name' => $ingredient->name,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '480',
            'subdivisions' => '24',
            'subdivision_label' => 'galleta',
        ])
        ->assertRedirect(route('ingredients.index'));

    $ingredient->refresh();

    expect((float) $ingredient->cost_per_package)->toBe(480.0)
        ->and((float) $ingredient->cost_per_unit)->toBe(20.0);
});

test('quitar subdivisiones al editar limpia cost_per_package', function () {
    [$user, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Unidad,
        'cost_per_unit' => '10',
        'cost_per_package' => '240',
        'subdivisions' => 24,
    ]);

    $this->actingAs($user)
        ->put(route('ingredients.update', $ingredient), [
            'name' => $ingredient->name,
            'unit' => Unit::Unidad->value,
            'cost_per_unit' => '50',
        ])
        ->assertRedirect(route('ingredients.index'));

    $ingredient->refresh();

    expect($ingredient->cost_per_package)->toBeNull()
        ->and((float) $ingredient->cost_per_unit)->toBe(50.0);
});

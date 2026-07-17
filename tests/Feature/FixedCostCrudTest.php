<?php

use App\Enums\TenantUserRole;
use App\Models\FixedCost;
use App\Models\FixedCostCategory;
use App\Models\FixedCostLog;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForFixedCost(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);
    $category = $tenant->fixedCostCategories()->create(['name' => 'General']);

    return [$user, $tenant, $category];
}

// --- CRUD ---

test('owner puede listar gastos fijos', function () {
    [$user, $tenant, $category] = ownerForFixedCost();
    FixedCost::factory()->for($tenant)->create(['name' => 'Alquiler local', 'fixed_cost_category_id' => $category->id]);

    $this->actingAs($user)
        ->get(route('fixed-costs.index'))
        ->assertOk()
        ->assertSee('Alquiler local');
});

test('viewer puede ver la lista de gastos fijos', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);
    $category = $tenant->fixedCostCategories()->create(['name' => 'Servicios']);
    FixedCost::factory()->for($tenant)->create(['name' => 'Internet', 'fixed_cost_category_id' => $category->id]);

    $this->actingAs($user)
        ->get(route('fixed-costs.index'))
        ->assertOk()
        ->assertSee('Internet');
});

test('owner puede crear un gasto fijo', function () {
    [$user, $tenant, $category] = ownerForFixedCost();

    $this->actingAs($user)
        ->post(route('fixed-costs.store'), [
            'name' => 'Seguro',
            'fixed_cost_category_id' => $category->id,
            'monthly_amount' => '5000.00',
            'valid_from' => '2024-01-01',
        ])
        ->assertRedirect(route('fixed-costs.index'));

    expect($tenant->fixedCosts()->where('name', 'Seguro')->exists())->toBeTrue();
});

test('admin puede crear un gasto fijo', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Admin->value,
        'active' => true,
    ]);
    $category = $tenant->fixedCostCategories()->create(['name' => 'Infraestructura']);

    $this->actingAs($user)
        ->post(route('fixed-costs.store'), [
            'name' => 'Electricidad',
            'fixed_cost_category_id' => $category->id,
            'monthly_amount' => '12000.00',
            'valid_from' => '2024-06-01',
        ])
        ->assertRedirect(route('fixed-costs.index'));

    expect($tenant->fixedCosts()->where('name', 'Electricidad')->exists())->toBeTrue();
});

test('viewer no puede crear gastos fijos', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);
    $category = $tenant->fixedCostCategories()->create(['name' => 'Servicios']);

    $this->actingAs($user)
        ->post(route('fixed-costs.store'), [
            'name' => 'Gas',
            'fixed_cost_category_id' => $category->id,
            'monthly_amount' => '3000',
            'valid_from' => '2024-01-01',
        ])
        ->assertForbidden();
});

test('nombre es obligatorio al crear gasto fijo', function () {
    [$user, $tenant, $category] = ownerForFixedCost();

    $this->actingAs($user)
        ->post(route('fixed-costs.store'), [
            'fixed_cost_category_id' => $category->id,
            'monthly_amount' => '1000',
            'valid_from' => '2024-01-01',
        ])
        ->assertSessionHasErrors('name');
});

test('category es obligatoria al crear gasto fijo', function () {
    [$user] = ownerForFixedCost();

    $this->actingAs($user)
        ->post(route('fixed-costs.store'), [
            'name' => 'Sin categoría',
            'monthly_amount' => '1000',
            'valid_from' => '2024-01-01',
        ])
        ->assertSessionHasErrors('fixed_cost_category_id');
});

test('owner puede editar un gasto fijo', function () {
    [$user, $tenant, $category] = ownerForFixedCost();
    $fixedCost = FixedCost::factory()->for($tenant)->create([
        'name' => 'Original',
        'fixed_cost_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->put(route('fixed-costs.update', $fixedCost), [
            'name' => 'Actualizado',
            'fixed_cost_category_id' => $category->id,
            'monthly_amount' => $fixedCost->monthly_amount,
            'valid_from' => date('Y-m-d'),
        ])
        ->assertRedirect(route('fixed-costs.index'));

    expect($fixedCost->fresh()->name)->toBe('Actualizado');
});

test('owner puede desactivar un gasto fijo', function () {
    [$user, $tenant, $category] = ownerForFixedCost();
    $fixedCost = FixedCost::factory()->for($tenant)->create([
        'active' => true,
        'fixed_cost_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->patch(route('fixed-costs.toggle-active', $fixedCost))
        ->assertRedirect();

    expect($fixedCost->fresh()->active)->toBeFalse();
});

// --- Logs ---

test('al crear un gasto fijo se registra un log', function () {
    [$user, $tenant, $category] = ownerForFixedCost();

    $this->actingAs($user)
        ->post(route('fixed-costs.store'), [
            'name' => 'Alquiler',
            'fixed_cost_category_id' => $category->id,
            'monthly_amount' => '80000.00',
            'valid_from' => '2024-01-01',
        ]);

    $fixedCost = $tenant->fixedCosts()->where('name', 'Alquiler')->first();

    expect($fixedCost->logs()->count())->toBe(1);
    expect((float) $fixedCost->logs()->first()->monthly_amount)->toBe(80000.0);
    expect($fixedCost->logs()->first()->valid_from->format('Y-m-d'))->toBe('2024-01-01');
});

test('al actualizar el monto se registra un nuevo log', function () {
    [$user, $tenant, $category] = ownerForFixedCost();
    $fixedCost = FixedCost::factory()->for($tenant)->create([
        'monthly_amount' => '10000.00',
        'fixed_cost_category_id' => $category->id,
    ]);
    $fixedCost->logs()->create(['monthly_amount' => '10000.00', 'valid_from' => '2024-01-01']);

    $this->actingAs($user)
        ->put(route('fixed-costs.update', $fixedCost), [
            'name' => $fixedCost->name,
            'fixed_cost_category_id' => $category->id,
            'monthly_amount' => '15000.00',
            'valid_from' => '2024-06-01',
        ]);

    expect($fixedCost->logs()->count())->toBe(2);
    expect((float) $fixedCost->logs()->orderByDesc('id')->first()->monthly_amount)->toBe(15000.0);
    expect($fixedCost->logs()->orderByDesc('id')->first()->valid_from->format('Y-m-d'))->toBe('2024-06-01');
});

test('al actualizar sin cambiar el monto no se genera un nuevo log', function () {
    [$user, $tenant, $category] = ownerForFixedCost();
    $fixedCost = FixedCost::factory()->for($tenant)->create([
        'monthly_amount' => '10000.00',
        'fixed_cost_category_id' => $category->id,
    ]);
    $fixedCost->logs()->create(['monthly_amount' => '10000.00', 'valid_from' => '2024-01-01']);

    $this->actingAs($user)
        ->put(route('fixed-costs.update', $fixedCost), [
            'name' => 'Nombre nuevo',
            'fixed_cost_category_id' => $category->id,
            'monthly_amount' => '10000.00',
            'valid_from' => date('Y-m-d'),
        ]);

    expect($fixedCost->logs()->count())->toBe(1);
});

test('los logs de gastos fijos no tienen timestamps', function () {
    $log = new FixedCostLog;
    expect($log->timestamps)->toBeFalse();
});

// --- Categorías ---

test('owner puede crear una categoría', function () {
    [$user, $tenant] = ownerForFixedCost();

    $this->actingAs($user)
        ->post(route('fixed-cost-categories.store'), ['name' => 'Personal'])
        ->assertRedirect(route('fixed-costs.index'));

    expect($tenant->fixedCostCategories()->where('name', 'Personal')->exists())->toBeTrue();
});

test('owner puede renombrar una categoría', function () {
    [$user, $tenant, $category] = ownerForFixedCost();

    $this->actingAs($user)
        ->put(route('fixed-cost-categories.update', $category), ['name' => 'Renombrada'])
        ->assertRedirect(route('fixed-costs.index'));

    expect($category->fresh()->name)->toBe('Renombrada');
});

test('owner puede eliminar una categoría sin gastos', function () {
    [$user, $tenant, $category] = ownerForFixedCost();

    $this->actingAs($user)
        ->delete(route('fixed-cost-categories.destroy', $category))
        ->assertRedirect(route('fixed-costs.index'));

    expect(FixedCostCategory::find($category->id))->toBeNull();
});

test('no se puede eliminar una categoría con gastos asignados', function () {
    [$user, $tenant, $category] = ownerForFixedCost();
    FixedCost::factory()->for($tenant)->create(['fixed_cost_category_id' => $category->id]);

    $this->actingAs($user)
        ->delete(route('fixed-cost-categories.destroy', $category))
        ->assertRedirect(route('fixed-costs.index'));

    expect(FixedCostCategory::find($category->id))->not->toBeNull();
});

test('aislamiento: no se puede eliminar categoría de otro tenant', function () {
    [$user] = ownerForFixedCost();

    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->fixedCostCategories()->create(['name' => 'Ajena']);

    $this->actingAs($user)
        ->delete(route('fixed-cost-categories.destroy', $otherCategory))
        ->assertNotFound();
});

// --- Aislamiento ---

test('aislamiento: gastos fijos de otro tenant no aparecen en el listado', function () {
    [$user] = ownerForFixedCost();

    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->fixedCostCategories()->create(['name' => 'Ext']);
    FixedCost::factory()->for($otherTenant)->create(['name' => 'AjenoFC', 'fixed_cost_category_id' => $otherCategory->id]);

    $this->actingAs($user)
        ->get(route('fixed-costs.index'))
        ->assertDontSee('AjenoFC');
});

test('aislamiento: no se puede editar gasto fijo de otro tenant', function () {
    [$user, , $category] = ownerForFixedCost();

    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->fixedCostCategories()->create(['name' => 'Ext']);
    $other = FixedCost::factory()->for($otherTenant)->create(['fixed_cost_category_id' => $otherCategory->id]);

    $this->actingAs($user)
        ->put(route('fixed-costs.update', $other), [
            'name' => 'Hack',
            'fixed_cost_category_id' => $category->id,
            'monthly_amount' => '1',
            'valid_from' => date('Y-m-d'),
        ])
        ->assertNotFound();
});

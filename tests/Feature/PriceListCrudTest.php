<?php

use App\Enums\TenantUserRole;
use App\Models\PriceList;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function userForPriceList(string $role = 'owner'): array
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

test('owner puede listar las listas de precios', function () {
    [$user, $tenant] = userForPriceList();
    PriceList::factory()->for($tenant)->create(['name' => 'Mayorista']);

    $this->actingAs($user)
        ->get(route('price-lists.index'))
        ->assertOk()
        ->assertSee('Mayorista');
});

test('el índice crea y muestra la lista base General', function () {
    [$user, $tenant] = userForPriceList();

    $this->actingAs($user)
        ->get(route('price-lists.index'))
        ->assertOk()
        ->assertSee('General');

    expect($tenant->priceLists()->where('is_default', true)->count())->toBe(1);
});

test('viewer ve el índice pero no el botón de crear', function () {
    [$user, $tenant] = userForPriceList(TenantUserRole::Viewer->value);
    PriceList::factory()->for($tenant)->create(['name' => 'Cafeterías']);

    $this->actingAs($user)
        ->get(route('price-lists.index'))
        ->assertOk()
        ->assertSee('Cafeterías')
        ->assertDontSee('btn-nueva-lista');
});

test('owner puede crear una lista con % de ajuste', function () {
    [$user, $tenant] = userForPriceList();

    $this->actingAs($user)
        ->post(route('price-lists.store'), [
            'name' => 'Mayorista',
            'adjustment_pct' => '-15',
        ])
        ->assertRedirect(route('price-lists.index'));

    $list = $tenant->priceLists()->where('name', 'Mayorista')->first();
    expect($list)->not->toBeNull()
        ->and((float) $list->adjustment_pct)->toBe(-15.0)
        ->and($list->is_default)->toBeFalse();
});

test('admin puede crear una lista', function () {
    [$user, $tenant] = userForPriceList(TenantUserRole::Admin->value);

    $this->actingAs($user)
        ->post(route('price-lists.store'), ['name' => 'Reventa'])
        ->assertRedirect(route('price-lists.index'));

    expect($tenant->priceLists()->where('name', 'Reventa')->exists())->toBeTrue();
});

test('viewer no puede crear listas', function () {
    [$user] = userForPriceList(TenantUserRole::Viewer->value);

    $this->actingAs($user)
        ->post(route('price-lists.store'), ['name' => 'Mayorista'])
        ->assertForbidden();
});

test('no se puede repetir el nombre dentro del mismo tenant', function () {
    [$user, $tenant] = userForPriceList();
    PriceList::factory()->for($tenant)->create(['name' => 'Mayorista']);

    $this->actingAs($user)
        ->post(route('price-lists.store'), ['name' => 'Mayorista'])
        ->assertSessionHasErrors('name');
});

test('el mismo nombre puede existir en otro tenant', function () {
    [$user, $tenant] = userForPriceList();
    $otherTenant = Tenant::factory()->create();
    PriceList::factory()->for($otherTenant)->create(['name' => 'Mayorista']);

    $this->actingAs($user)
        ->post(route('price-lists.store'), ['name' => 'Mayorista'])
        ->assertRedirect(route('price-lists.index'));

    expect($tenant->priceLists()->where('name', 'Mayorista')->exists())->toBeTrue();
});

test('owner puede editar nombre y % de ajuste', function () {
    [$user, $tenant] = userForPriceList();
    $list = PriceList::factory()->for($tenant)->create(['name' => 'Mayorista', 'adjustment_pct' => -10]);

    $this->actingAs($user)
        ->put(route('price-lists.update', $list), [
            'name' => 'Mayorista CABA',
            'adjustment_pct' => '-20',
        ])
        ->assertRedirect(route('price-lists.index'));

    $list->refresh();
    expect($list->name)->toBe('Mayorista CABA')
        ->and((float) $list->adjustment_pct)->toBe(-20.0);
});

test('la lista default no acepta % de ajuste', function () {
    [$user, $tenant] = userForPriceList();
    $list = $tenant->defaultPriceList();

    $this->actingAs($user)
        ->put(route('price-lists.update', $list), [
            'name' => 'Mostrador',
            'adjustment_pct' => '-15',
        ])
        ->assertRedirect(route('price-lists.index'));

    $list->refresh();
    expect($list->name)->toBe('Mostrador')
        ->and($list->adjustment_pct)->toBeNull();
});

test('toggle-active desactiva y reactiva una lista no default', function () {
    [$user, $tenant] = userForPriceList();
    $list = PriceList::factory()->for($tenant)->create();

    $this->actingAs($user)->patch(route('price-lists.toggle-active', $list));
    expect($list->refresh()->active)->toBeFalse();

    $this->actingAs($user)->patch(route('price-lists.toggle-active', $list));
    expect($list->refresh()->active)->toBeTrue();
});

test('la lista default no se puede desactivar', function () {
    [$user, $tenant] = userForPriceList();
    $list = $tenant->defaultPriceList();

    $this->actingAs($user)
        ->patch(route('price-lists.toggle-active', $list))
        ->assertSessionHasErrors('toggle');

    expect($list->refresh()->active)->toBeTrue();
});

test('aislamiento: no se puede editar ni desactivar una lista de otro tenant', function () {
    [$user] = userForPriceList();
    $otherTenant = Tenant::factory()->create();
    $foreignList = PriceList::factory()->for($otherTenant)->create(['name' => 'Ajena']);

    $this->actingAs($user)
        ->put(route('price-lists.update', $foreignList), ['name' => 'Hackeada'])
        ->assertNotFound();

    $this->actingAs($user)
        ->patch(route('price-lists.toggle-active', $foreignList))
        ->assertNotFound();

    expect($foreignList->refresh()->name)->toBe('Ajena');
});

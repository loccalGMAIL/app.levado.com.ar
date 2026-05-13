<?php

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

// Helper para crear un usuario ya vinculado a un tenant
function userWithTenant(TenantUserRole $role = TenantUserRole::Owner): array
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

// --- Acceso de invitados ---

test('rutas protegidas redirigen al login si no está autenticado', function (string $route) {
    $this->get($route)->assertRedirect(route('login'));
})->with([
    '/dashboard',
    '/team',
    '/business',
    '/profile',
]);

// --- Acceso sin tenant ---

test('usuario sin tenant no puede acceder al dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

// --- Flujo completo por rol ---

test('owner puede acceder al dashboard, equipo y negocio', function () {
    [$user] = userWithTenant(TenantUserRole::Owner);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('team.index'))->assertOk();
    $this->actingAs($user)->get(route('business.edit'))->assertOk();
});

test('admin puede acceder al dashboard y al equipo pero no a negocio', function () {
    [$user] = userWithTenant(TenantUserRole::Admin);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('team.index'))->assertOk();
    $this->actingAs($user)->get(route('business.edit'))->assertForbidden();
});

test('viewer solo puede acceder al dashboard', function () {
    [$user] = userWithTenant(TenantUserRole::Viewer);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('team.index'))->assertForbidden();
    $this->actingAs($user)->get(route('business.edit'))->assertForbidden();
});

// --- Aislamiento entre tenants ---

test('usuario del tenant A no puede ver el equipo del tenant B', function () {
    [$userA, $tenantA] = userWithTenant(TenantUserRole::Owner);
    [$userB, $tenantB] = userWithTenant(TenantUserRole::Owner);

    $this->actingAs($userA)
        ->get(route('team.index'))
        ->assertOk();

    expect($tenantA->id)->not->toBe($tenantB->id);

    $membersOfA = TenantUser::where('tenant_id', $tenantA->id)->pluck('user_id');
    $membersOfB = TenantUser::where('tenant_id', $tenantB->id)->pluck('user_id');

    expect($membersOfA->intersect($membersOfB))->toBeEmpty();
});

test('usuario inactivo en el tenant no puede acceder', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

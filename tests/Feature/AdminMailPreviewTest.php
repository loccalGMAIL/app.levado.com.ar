<?php

use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function superAdminUser(): User
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::SuperAdmin->value,
        'active' => true,
    ]);

    return $user;
}

test('super admin puede acceder al índice de emails', function () {
    $this->actingAs(superAdminUser())
        ->get(route('admin.mails.index'))
        ->assertOk();
});

test('super admin puede previsualizar el email de invitación', function () {
    $this->actingAs(superAdminUser())
        ->get(route('admin.mails.preview.team-invitation'))
        ->assertOk()
        ->assertSee('Panaderia El Trigo de Oro')
        ->assertSee('Aceptar invitación');
});

test('super admin puede previsualizar el email de bienvenida', function () {
    $this->actingAs(superAdminUser())
        ->get(route('admin.mails.preview.welcome'))
        ->assertOk()
        ->assertSee('Maria Gonzalez')
        ->assertSee('Ingresar a Levado');
});

test('usuario sin rol super admin no puede acceder a la preview de emails', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('admin.mails.index'))
        ->assertForbidden();
});

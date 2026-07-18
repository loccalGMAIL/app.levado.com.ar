<?php

use App\Enums\TenantUserRole;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

function ownerForErrorPages(): array
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

test('el 404 muestra la página amigable con branding', function () {
    [$user] = ownerForErrorPages();

    $this->actingAs($user)
        ->get('/recipes/999999')
        ->assertNotFound()
        ->assertSee('No encontramos esa página')
        ->assertSee('no pertenece a tu negocio');
});

test('un recurso de otro tenant muestra el mismo 404 amigable', function () {
    [$user] = ownerForErrorPages();
    $otherTenant = Tenant::factory()->create();
    $foreign = Recipe::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->get(route('recipes.show', $foreign))
        ->assertNotFound()
        ->assertSee('No encontramos esa página');
});

test('el 403 muestra la página amigable en español', function () {
    $tenant = Tenant::factory()->create();
    $viewer = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $viewer->id,
        'role' => TenantUserRole::Viewer->value,
        'active' => true,
    ]);

    $this->actingAs($viewer)
        ->post(route('recipes.store'), ['name' => 'X', 'yield_quantity' => '1', 'yield_unit' => 'u'])
        ->assertForbidden()
        ->assertSee('No tenés permiso')
        ->assertDontSee('This action is unauthorized');
});

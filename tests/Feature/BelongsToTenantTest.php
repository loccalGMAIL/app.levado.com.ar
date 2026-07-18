<?php

use App\Enums\TenantUserRole;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\App;

// Anclas del trait BelongsToTenant: el aislamiento entre tenants es
// estructural (global scope + auto-fill), no depende de que cada
// controller se acuerde de scopear.

function ownerForTenantScope(): array
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

test('con tenant resuelto, las queries solo ven registros del tenant', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    Ingredient::factory()->for($tenant)->create(['name' => 'Propio']);
    Ingredient::factory()->for($otherTenant)->create(['name' => 'Ajeno']);

    App::instance(Tenant::class, $tenant);

    // Query "olvidadiza" sin scopear a mano: el scope global la acota igual.
    expect(Ingredient::pluck('name')->all())->toBe(['Propio'])
        ->and(Ingredient::count())->toBe(1);
});

test('sin tenant resuelto no hay scope (contexto admin o consola)', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    Ingredient::factory()->for($tenant)->create();
    Ingredient::factory()->for($otherTenant)->create();

    expect(Ingredient::count())->toBe(2);
});

test('crear con tenant resuelto completa tenant_id automáticamente', function () {
    $tenant = Tenant::factory()->create();
    App::instance(Tenant::class, $tenant);

    $ingredient = Ingredient::create([
        'name' => 'Harina',
        'unit' => 'kg',
        'cost_per_unit' => 100,
        'active' => true,
    ]);

    expect($ingredient->tenant_id)->toBe($tenant->id);
});

test('el auto-fill no pisa un tenant_id explícito', function () {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    App::instance(Tenant::class, $tenant);

    $ingredient = Ingredient::factory()->for($otherTenant)->create();

    expect($ingredient->tenant_id)->toBe($otherTenant->id);
});

test('el binding de rutas queda acotado: un recurso ajeno da 404 aunque el controller no scopee', function () {
    [$user] = ownerForTenantScope();
    $otherTenant = Tenant::factory()->create();
    $foreign = Recipe::factory()->for($otherTenant)->create();

    $this->actingAs($user)
        ->get(route('recipes.show', $foreign))
        ->assertNotFound();
});

test('impersonando un tenant, los recursos de otros tenants dan 404 incluso para el super admin', function () {
    // Mitiga el hallazgo S3 de la auditoría: Gate::before saltea las policies
    // para super admins, pero el scope estructural igual oculta los datos de
    // los tenants no impersonados.
    $hq = Tenant::factory()->create(['name' => 'Levado HQ']);
    $superAdmin = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $hq->id,
        'user_id' => $superAdmin->id,
        'role' => TenantUserRole::SuperAdmin->value,
        'active' => true,
    ]);

    $impersonated = Tenant::factory()->create();
    $bystander = Tenant::factory()->create();
    $visible = Recipe::factory()->for($impersonated)->create();
    $hidden = Recipe::factory()->for($bystander)->create();

    $session = ['impersonating_tenant_id' => $impersonated->id, 'impersonating_by_user_id' => $superAdmin->id];

    $this->actingAs($superAdmin)->withSession($session)
        ->get(route('recipes.show', $visible))
        ->assertOk();

    $this->actingAs($superAdmin)->withSession($session)
        ->get(route('recipes.show', $hidden))
        ->assertNotFound();
});

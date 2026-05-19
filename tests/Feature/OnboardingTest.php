<?php

use App\Enums\TenantUserRole;
use App\Models\FixedCost;
use App\Models\Ingredient;
use App\Models\LaborType;
use App\Models\Recipe;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

// Creates an owner for a fresh empty tenant (no onboarding_completed_at, no productive hours)
function freshOwner(): array
{
    $tenant = Tenant::factory()->create([
        'onboarding_completed_at' => null,
        'productive_hours_month' => 0,
    ]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    return [$user, $tenant];
}

// ── hasCompletedOnboarding ────────────────────────────────────────────────────

test('tenant sin recetas ni timestamp no ha completado el onboarding', function () {
    [$user, $tenant] = freshOwner();

    expect($tenant->hasCompletedOnboarding())->toBeFalse();
});

test('tenant con onboarding_completed_at ya completó el onboarding', function () {
    [$user, $tenant] = freshOwner();
    $tenant->update(['onboarding_completed_at' => now()]);

    expect($tenant->hasCompletedOnboarding())->toBeTrue();
});

test('tenant con recetas existentes ya completó el onboarding aunque no tenga timestamp', function () {
    [$user, $tenant] = freshOwner();
    Recipe::factory()->for($tenant)->create();

    expect($tenant->hasCompletedOnboarding())->toBeTrue();
});

// ── ViewComposer — window.levadoOnboarding ────────────────────────────────────

test('dashboard inyecta step 0 cuando tenant no tiene horas productivas configuradas', function () {
    [$user, $tenant] = freshOwner(); // productive_hours_month = 0

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('window.levadoOnboarding', false)
        ->assertSee('step: 0,', false);
});

test('dashboard inyecta step 1 cuando tenant tiene horas pero no tiene gastos fijos', function () {
    [$user, $tenant] = freshOwner();
    $tenant->update(['productive_hours_month' => 160]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('step: 1,', false);
});

test('dashboard inyecta step 2 cuando tenant tiene gastos fijos pero no mano de obra', function () {
    [$user, $tenant] = freshOwner();
    $tenant->update(['productive_hours_month' => 160]);
    FixedCost::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('step: 2,', false);
});

test('dashboard inyecta step 3 cuando tenant tiene mano de obra pero no ingredientes', function () {
    [$user, $tenant] = freshOwner();
    $tenant->update(['productive_hours_month' => 160]);
    FixedCost::factory()->for($tenant)->create();
    LaborType::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('step: 3,', false);
});

test('dashboard inyecta step 4 cuando tenant tiene ingredientes pero no recetas', function () {
    [$user, $tenant] = freshOwner();
    $tenant->update(['productive_hours_month' => 160]);
    FixedCost::factory()->for($tenant)->create();
    LaborType::factory()->for($tenant)->create();
    Ingredient::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('step: 4,', false);
});

test('dashboard no inyecta levadoOnboarding cuando el onboarding ya fue completado', function () {
    [$user, $tenant] = freshOwner();
    $tenant->update(['onboarding_completed_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('window.levadoOnboarding', false);
});

test('dashboard no inyecta levadoOnboarding cuando tenant ya tiene recetas', function () {
    [$user, $tenant] = freshOwner();
    Recipe::factory()->for($tenant)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('window.levadoOnboarding', false);
});

// ── RecipeController — set onboarding_completed_at ───────────────────────────

test('crear la primera receta setea onboarding_completed_at', function () {
    [$user, $tenant] = freshOwner();

    $this->actingAs($user)
        ->post(route('recipes.store'), [
            'name' => 'Pan de campo',
            'yield_quantity' => '10',
            'yield_unit' => 'u',
        ]);

    expect($tenant->fresh()->onboarding_completed_at)->not->toBeNull();
});

test('crear una receta adicional no sobreescribe onboarding_completed_at existente', function () {
    [$user, $tenant] = freshOwner();
    $original = now()->subDay();
    $tenant->update(['onboarding_completed_at' => $original]);

    $this->actingAs($user)
        ->post(route('recipes.store'), [
            'name' => 'Segunda receta',
            'yield_quantity' => '5',
            'yield_unit' => 'u',
        ]);

    expect($tenant->fresh()->onboarding_completed_at->toDateString())
        ->toBe($original->toDateString());
});

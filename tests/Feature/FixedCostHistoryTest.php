<?php

use App\Enums\TenantUserRole;
use App\Models\FixedCost;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\FixedCostHistory;
use Illuminate\Support\Carbon;

function tenantWithFixedCostRole(TenantUserRole $role): array
{
    $tenant = Tenant::factory()->create(['productive_hours_month' => 160]);
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => $role->value,
        'active' => true,
    ]);
    $category = $tenant->fixedCostCategories()->create(['name' => 'General']);

    return [$user, $tenant, $category];
}

// --- Carry-forward ---

test('un gasto sin log en el mes vale lo último cargado antes', function () {
    [, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $fixedCost = FixedCost::factory()->for($tenant)->create([
        'fixed_cost_category_id' => $category->id,
        'monthly_amount' => '5000.00',
    ]);
    $fixedCost->logs()->create(['monthly_amount' => '5000.00', 'period' => Carbon::now()->subMonths(4)->startOfMonth()]);

    $amounts = app(FixedCostHistory::class)->amountsForPeriod($tenant, Carbon::now());

    expect($amounts->get($fixedCost->id)['amount'])->toBe(5000.0);
    expect($amounts->get($fixedCost->id)['carried'])->toBeTrue();
});

test('totalForPeriod suma arrastrados y cargados, y un 0 explicito no cuenta', function () {
    [, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $rent = FixedCost::factory()->for($tenant)->create(['fixed_cost_category_id' => $category->id, 'monthly_amount' => '10000.00']);
    $rent->logs()->create(['monthly_amount' => '10000.00', 'period' => Carbon::now()->subMonths(2)->startOfMonth()]);

    $internet = FixedCost::factory()->for($tenant)->create(['fixed_cost_category_id' => $category->id, 'monthly_amount' => '0.00']);
    $internet->logs()->create(['monthly_amount' => '0.00', 'period' => Carbon::now()->startOfMonth()]);

    $total = app(FixedCostHistory::class)->totalForPeriod($tenant, Carbon::now());

    expect($total)->toBe(10000.0);
});

// --- Grilla mensual ---

test('guardar la grilla del mes en curso actualiza el monto vigente y el overhead', function () {
    [$user, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $fixedCost = FixedCost::factory()->for($tenant)->create([
        'fixed_cost_category_id' => $category->id,
        'monthly_amount' => '16000.00',
    ]);
    $fixedCost->logs()->create(['monthly_amount' => '16000.00', 'period' => Carbon::now()->startOfMonth()]);

    $this->actingAs($user)
        ->post(route('fixed-costs.history.store'), [
            'period' => Carbon::now()->format('Y-m'),
            'amounts' => [$fixedCost->id => '32000.00'],
        ])
        ->assertRedirect();

    expect((float) $fixedCost->fresh()->monthly_amount)->toBe(32000.0);
    expect($tenant->fresh()->overheadPerHour())->toBe(200.0);
});

test('guardar un mes pasado no toca el monto vigente ni el overhead', function () {
    [$user, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $fixedCost = FixedCost::factory()->for($tenant)->create([
        'fixed_cost_category_id' => $category->id,
        'monthly_amount' => '16000.00',
    ]);
    $fixedCost->logs()->create(['monthly_amount' => '16000.00', 'period' => Carbon::now()->startOfMonth()]);
    $pastPeriod = Carbon::now()->subMonths(3);

    $this->actingAs($user)
        ->post(route('fixed-costs.history.store'), [
            'period' => $pastPeriod->format('Y-m'),
            'amounts' => [$fixedCost->id => '9000.00'],
        ])
        ->assertRedirect();

    expect((float) $fixedCost->fresh()->monthly_amount)->toBe(16000.0);
    expect($tenant->fresh()->overheadPerHour())->toBe(100.0);
    expect($fixedCost->logs()->where('period', $pastPeriod->startOfMonth()->toDateString())->exists())->toBeTrue();
});

test('guardar dos veces el mismo mes deja un solo registro con el ultimo valor', function () {
    [$user, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $fixedCost = FixedCost::factory()->for($tenant)->create(['fixed_cost_category_id' => $category->id]);
    $period = Carbon::now()->subMonth();

    $this->actingAs($user)->post(route('fixed-costs.history.store'), [
        'period' => $period->format('Y-m'),
        'amounts' => [$fixedCost->id => '1000.00'],
    ]);
    $this->actingAs($user)->post(route('fixed-costs.history.store'), [
        'period' => $period->format('Y-m'),
        'amounts' => [$fixedCost->id => '1500.00'],
    ]);

    expect($fixedCost->logs()->where('period', $period->startOfMonth()->toDateString())->count())->toBe(1);
    expect((float) $fixedCost->logs()->where('period', $period->startOfMonth()->toDateString())->first()->monthly_amount)->toBe(1500.0);
});

test('viewer ve el historial pero no puede guardar la grilla', function () {
    [$user, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Viewer);
    $fixedCost = FixedCost::factory()->for($tenant)->create(['fixed_cost_category_id' => $category->id]);

    $this->actingAs($user)->get(route('fixed-costs.history'))->assertOk();

    $this->actingAs($user)
        ->post(route('fixed-costs.history.store'), [
            'period' => Carbon::now()->format('Y-m'),
            'amounts' => [$fixedCost->id => '1000.00'],
        ])
        ->assertForbidden();
});

// --- Activar/desactivar ---

test('desactivar un gasto fijo registra 0 en el periodo en curso', function () {
    [$user, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $fixedCost = FixedCost::factory()->for($tenant)->create([
        'fixed_cost_category_id' => $category->id,
        'monthly_amount' => '8000.00',
        'active' => true,
    ]);
    $fixedCost->logs()->create(['monthly_amount' => '8000.00', 'period' => Carbon::now()->subMonth()->startOfMonth()]);

    $this->actingAs($user)->patch(route('fixed-costs.toggle-active', $fixedCost));

    $amounts = app(FixedCostHistory::class)->amountsForPeriod($tenant, Carbon::now());
    expect($amounts->get($fixedCost->id)['amount'])->toBe(0.0);
});

test('reactivar un gasto fijo registra el monto vigente en el periodo en curso', function () {
    [$user, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $fixedCost = FixedCost::factory()->for($tenant)->create([
        'fixed_cost_category_id' => $category->id,
        'monthly_amount' => '8000.00',
        'active' => false,
    ]);

    $this->actingAs($user)->patch(route('fixed-costs.toggle-active', $fixedCost));

    $amounts = app(FixedCostHistory::class)->amountsForPeriod($tenant, Carbon::now());
    expect($amounts->get($fixedCost->id)['amount'])->toBe(8000.0);
});

// --- Invariante ---

test('el total historico del mes en curso coincide con totalFixedCosts', function () {
    [, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    FixedCost::factory()->for($tenant)->withHistory(6)->create(['fixed_cost_category_id' => $category->id, 'monthly_amount' => '4000.00']);
    FixedCost::factory()->for($tenant)->withHistory(3)->create(['fixed_cost_category_id' => $category->id, 'monthly_amount' => '6000.00']);
    FixedCost::factory()->for($tenant)->withHistory(1)->create(['fixed_cost_category_id' => $category->id, 'monthly_amount' => '1500.00', 'active' => false]);

    $historicoDelMes = app(FixedCostHistory::class)->totalForPeriod($tenant, Carbon::now());

    // El gasto inactivo no tiene registro de "0" (no pasó por toggleActive),
    // así que a diferencia de totalFixedCosts() (que filtra por `active`),
    // el histórico todavía lo cuenta con su último monto cargado -es el
    // comportamiento esperado: el histórico responde "qué regía", no "qué
    // sigue activo hoy".
    expect($historicoDelMes)->toBe($tenant->fresh()->totalFixedCosts() + 1500.0);
});

// --- Aislamiento ---

test('aislamiento: no se puede guardar la grilla con gastos de otro tenant', function () {
    [$user, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->fixedCostCategories()->create(['name' => 'Ajena']);
    $otherFixedCost = FixedCost::factory()->for($otherTenant)->create(['fixed_cost_category_id' => $otherCategory->id, 'monthly_amount' => '100.00']);

    $this->actingAs($user)
        ->post(route('fixed-costs.history.store'), [
            'period' => Carbon::now()->format('Y-m'),
            'amounts' => [$otherFixedCost->id => '99999.00'],
        ])
        ->assertRedirect();

    expect((float) $otherFixedCost->fresh()->monthly_amount)->toBe(100.0);
    expect($otherFixedCost->logs()->count())->toBe(0);
});

test('aislamiento: no se puede ver el timeline de un gasto de otro tenant', function () {
    [$user] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->fixedCostCategories()->create(['name' => 'Ajena']);
    $otherFixedCost = FixedCost::factory()->for($otherTenant)->create(['fixed_cost_category_id' => $otherCategory->id]);

    // El global scope de BelongsToTenant ya excluye el registro ajeno del
    // route-model binding, así que nunca llega a evaluarse la policy: da 404,
    // no 403 -mismo comportamiento que el resto de los tests de aislamiento
    // de FixedCostCrudTest (ver "no se puede editar gasto fijo de otro tenant").
    $this->actingAs($user)
        ->get(route('fixed-costs.show-history', $otherFixedCost))
        ->assertNotFound();
});

// --- Timeline ---

test('el timeline calcula la variacion porcentual contra el registro anterior', function () {
    [, $tenant, $category] = tenantWithFixedCostRole(TenantUserRole::Owner);
    $fixedCost = FixedCost::factory()->for($tenant)->create(['fixed_cost_category_id' => $category->id, 'monthly_amount' => '1100.00']);
    $fixedCost->logs()->create(['monthly_amount' => '1000.00', 'period' => Carbon::now()->subMonths(2)->startOfMonth()]);
    $fixedCost->logs()->create(['monthly_amount' => '1100.00', 'period' => Carbon::now()->subMonth()->startOfMonth()]);

    $timeline = app(FixedCostHistory::class)->timelineFor($fixedCost);

    expect($timeline->first()['change_pct'])->toBeNull();
    expect($timeline->last()['change_pct'])->toBe(10.0);
});

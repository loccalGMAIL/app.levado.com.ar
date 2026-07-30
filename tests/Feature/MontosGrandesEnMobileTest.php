<?php

use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Models\FixedCost;
use App\Models\Ingredient;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\StockService;

// Anclas del arreglo de desborde: un importe de 8 o 9 dígitos se salía de las
// cards de resumen. El tamaño de la cifra lo resuelve CSS (.kpi-figure mide
// contra el ancho de la card, no del viewport), así que lo que se puede fijar
// desde acá es el marcado del que depende: que la cifra use el utilitario, que
// las cards de importe tomen las dos columnas mientras el grid sea de dos, y
// que el redondeo del KPI no se lleve el valor exacto.

function ownerConMontosGrandes(float $monthlyAmount): array
{
    $tenant = Tenant::factory()->create(['productive_hours_month' => 160]);
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    FixedCost::factory()->for($tenant)->create([
        'name' => 'Alquiler del local',
        'monthly_amount' => $monthlyAmount,
        'active' => true,
    ]);

    return [$user, $tenant];
}

test('la cifra de gastos fijos del dashboard se dimensiona contra el ancho de la card', function () {
    [$user] = ownerConMontosGrandes(123456789.00);

    $html = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('kpi-figure')
        ->and($html)->toContain('kpi-card');
});

test('las cards de importe del dashboard ocupan las dos columnas en mobile', function () {
    [$user] = ownerConMontosGrandes(123456789.00);

    $html = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    // Gastos fijos y Overhead por hora: full width mientras el grid sea de dos
    // columnas, una sola a partir de sm.
    expect(substr_count($html, 'col-span-2 order-3 sm:col-span-1'))->toBe(1)
        ->and(substr_count($html, 'col-span-2 order-4 sm:col-span-1'))->toBe(1);
});

test('el KPI de gastos fijos redondea a peso pero conserva el valor exacto en el title', function () {
    [$user] = ownerConMontosGrandes(123456789.45);

    $html = $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    // La cifra grande va sin centavos: son 3 caracteres que a este tamaño no sobran.
    expect($html)->toContain('$&nbsp;123.456.789')
        ->and($html)->not->toContain('$&nbsp;123.456.789,45')
        // …y el valor exacto sigue disponible al pasar el mouse.
        ->and($html)->toContain('title="$ 123.456.789,45 por mes"');
});

test('la valuación del kardex ocupa las dos columnas mientras el grid sea de dos', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => TenantUserRole::Owner->value,
        'active' => true,
    ]);

    $ingredient = Ingredient::factory()->for($tenant)->create([
        'unit' => Unit::Kilogramo,
        'cost_per_unit' => 123456.78,
    ]);
    app(StockService::class)->registerAdjustment($ingredient, $tenant->defaultLocation(), 1000, 'Carga inicial', $user);

    $html = $this->actingAs($user)
        ->get(route('stock.show', ['ingredient', $ingredient->id]))
        ->assertOk()
        ->getContent();

    // Valuación de 9 dígitos ($ 123.456.780,00) en la card más angosta de las cuatro.
    expect($html)->toContain('col-span-2 lg:col-span-1')
        ->and($html)->toContain('kpi-figure')
        // El 4-up arranca en lg: a 768px cuatro columnas dejan 87px por card.
        ->and($html)->toContain('grid grid-cols-2 lg:grid-cols-4');
});

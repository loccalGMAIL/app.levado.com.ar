<?php

use App\Enums\CondicionIva;
use App\Enums\TenantUserRole;
use App\Models\FixedCost;
use App\Models\FixedCostCategory;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\VariableExpense;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Setup propio -no se reusa `ownerForFixedCost()` de FixedCostCrudTest.php-
 * para no acoplar este archivo al orden en que Pest cargue los test files:
 * esa función se declara en el ámbito global recién cuando ese archivo se
 * ejecuta.
 *
 * @return array{0: User, 1: Tenant, 2: FixedCostCategory}
 */
function reportSetup(): array
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

test('el botón «Ver / Imprimir» navega en la misma pestaña, no abre una nueva', function () {
    // formtarget="_blank" abría una pestaña por cada intento sin cerrar las
    // anteriores -todas tituladas igual-, y era fácil terminar mirando una
    // vieja con un rango de fechas distinto al que se acababa de elegir.
    [$user] = reportSetup();

    $this->actingAs($user)
        ->get(route('fixed-costs.index'))
        ->assertOk()
        ->assertSee('Ver / Imprimir')
        ->assertDontSee('formtarget="_blank"', false);
});

test('el reporte lista los gastos vigentes con el total al pie', function () {
    [$user, $tenant, $category] = reportSetup();
    FixedCost::factory()->for($tenant)->for($category, 'category')->create(['name' => 'Alquiler', 'monthly_amount' => 120000])
        ->logs()->create(['monthly_amount' => 120000, 'period' => now()->startOfMonth()]);

    $this->actingAs($user)
        ->get(route('fixed-costs.report'))
        ->assertOk()
        ->assertSeeInOrder(['Alquiler', '120.000,00', 'Total']);
});

test('el reporte usa el monto vigente al cierre del rango, no el actual', function () {
    [$user, $tenant, $category] = reportSetup();
    $fixedCost = FixedCost::factory()->for($tenant)->for($category, 'category')->create(['name' => 'Luz', 'monthly_amount' => 5000]);
    $fixedCost->logs()->create(['monthly_amount' => 3000, 'period' => Carbon::create(2026, 7, 1)]);
    $fixedCost->logs()->create(['monthly_amount' => 5000, 'period' => Carbon::create(2026, 8, 1)]);

    $response = $this->actingAs($user)
        ->get(route('fixed-costs.report', ['to' => '2026-07-31']));

    $response->assertOk()
        ->assertSee('Julio 2026')
        ->assertSee('3.000,00')
        ->assertDontSee('5.000,00');
});

test('el encabezado trae los datos del negocio y la fecha de emisión', function () {
    $tenant = Tenant::factory()->create([
        'razon_social' => 'Panadería La Espiga SRL',
        'cuit' => '30-12345678-9',
        'condicion_iva' => CondicionIva::RI,
    ]);
    $user = User::factory()->create();
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => TenantUserRole::Owner->value, 'active' => true]);

    $this->actingAs($user)
        ->get(route('fixed-costs.report'))
        ->assertOk()
        ->assertSee('Panadería La Espiga SRL')
        ->assertSee('30-12345678-9')
        ->assertSee(now()->format('d/m/Y'));
});

test('el filtro por categoría acota la sección de gastos vigentes', function () {
    [$user, $tenant, $category] = reportSetup();
    $otherCategory = $tenant->fixedCostCategories()->create(['name' => 'Otra']);
    FixedCost::factory()->for($tenant)->for($category, 'category')->create(['name' => 'Alquiler'])
        ->logs()->create(['monthly_amount' => 100, 'period' => now()->startOfMonth()]);
    FixedCost::factory()->for($tenant)->for($otherCategory, 'category')->create(['name' => 'Internet'])
        ->logs()->create(['monthly_amount' => 200, 'period' => now()->startOfMonth()]);

    $this->actingAs($user)
        ->get(route('fixed-costs.report', ['category' => $category->id]))
        ->assertOk()
        ->assertSee('Alquiler')
        ->assertDontSee('Internet');
});

test('los filtros de gastos variables (ve_*) acotan esa sección del reporte', function () {
    [$user, $tenant] = reportSetup();
    $category = $tenant->variableExpenseCategories()->create(['name' => 'Mantenimiento']);
    $otherCategory = $tenant->variableExpenseCategories()->create(['name' => 'Otra']);
    VariableExpense::factory()->for($tenant)->create([
        'name' => 'Reparación horno',
        'variable_expense_category_id' => $category->id,
        'expense_date' => now()->startOfMonth()->addDays(2),
    ]);
    VariableExpense::factory()->for($tenant)->create([
        'name' => 'Compra de insumos',
        'variable_expense_category_id' => $otherCategory->id,
        'expense_date' => now()->startOfMonth()->addDays(3),
    ]);

    $this->actingAs($user)
        ->get(route('fixed-costs.report', [
            'sections' => ['variable'],
            'from' => now()->startOfMonth()->format('Y-m-d'),
            'to' => now()->endOfMonth()->format('Y-m-d'),
            've_category' => $category->id,
        ]))
        ->assertOk()
        ->assertSee('Reparación horno')
        ->assertDontSee('Compra de insumos');
});

test('el histórico mensual sólo aparece si se pide la sección', function () {
    [$user, $tenant, $category] = reportSetup();
    FixedCost::factory()->for($tenant)->for($category, 'category')->withHistory(3)->create(['name' => 'Internet']);

    $this->actingAs($user)
        ->get(route('fixed-costs.report', ['sections' => ['current']]))
        ->assertOk()
        ->assertDontSee('Histórico mensual');

    $this->actingAs($user)
        ->get(route('fixed-costs.report', ['sections' => ['current', 'monthly']]))
        ->assertOk()
        ->assertSee('Histórico mensual');
});

test('el reporte incluye los gastos variables del rango cuando se piden', function () {
    [$user, $tenant] = reportSetup();
    VariableExpense::factory()->for($tenant)->create(['name' => 'Reparación horno', 'amount' => 8000, 'expense_date' => now()->startOfMonth()->addDays(2)]);
    VariableExpense::factory()->for($tenant)->create(['name' => 'Gasto de otro mes', 'amount' => 1000, 'expense_date' => now()->subMonths(2)]);

    // Rango acotado al mes actual: el default (12 meses) incluiría los dos
    // gastos y no probaría nada.
    $this->actingAs($user)
        ->get(route('fixed-costs.report', [
            'sections' => ['variable'],
            'from' => now()->startOfMonth()->format('Y-m-d'),
            'to' => now()->endOfMonth()->format('Y-m-d'),
        ]))
        ->assertOk()
        ->assertSee('Reparación horno')
        ->assertDontSee('Gasto de otro mes');
});

test('el detalle por gasto no dispara una consulta por gasto', function () {
    [$user, $tenant, $category] = reportSetup();
    FixedCost::factory()->for($tenant)->for($category, 'category')->withHistory(4)->count(3)->create();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->actingAs($user)
        ->get(route('fixed-costs.report', ['sections' => ['current', 'details']]))
        ->assertOk();

    expect($queries)->toBeLessThan(15);
});

test('un tenant sin gastos fijos ve el reporte vacío', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantUser::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => TenantUserRole::Owner->value, 'active' => true]);

    $this->actingAs($user)
        ->get(route('fixed-costs.report'))
        ->assertOk()
        ->assertSee('No hay gastos fijos registrados para este período.');
});

test('aislamiento: el reporte no muestra gastos de otro tenant', function () {
    [$user, $tenant, $category] = reportSetup();
    FixedCost::factory()->for($tenant)->for($category, 'category')->create(['name' => 'Gasto propio']);

    $otherTenant = Tenant::factory()->create();
    $otherCategory = $otherTenant->fixedCostCategories()->create(['name' => 'Otra']);
    FixedCost::factory()->for($otherTenant)->for($otherCategory, 'category')->create(['name' => 'Gasto Ajeno']);

    $this->actingAs($user)
        ->get(route('fixed-costs.report'))
        ->assertOk()
        ->assertSee('Gasto propio')
        ->assertDontSee('Gasto Ajeno');
});

test('el rango invertido no genera reporte', function () {
    [$user] = reportSetup();

    $this->actingAs($user)
        ->get(route('fixed-costs.report', ['from' => '2026-09-30', 'to' => '2026-01-01']))
        ->assertSessionHasErrors('to');
});

test('el rango de más de 24 meses no genera reporte', function () {
    [$user] = reportSetup();

    $this->actingAs($user)
        ->get(route('fixed-costs.report', ['from' => '2023-01-01', 'to' => '2026-09-01']))
        ->assertSessionHasErrors('to');
});

test('la descarga genera un PDF', function () {
    [$user, $tenant, $category] = reportSetup();
    FixedCost::factory()->for($tenant)->for($category, 'category')->create(['name' => 'Alquiler', 'monthly_amount' => 120000])
        ->logs()->create(['monthly_amount' => 120000, 'period' => Carbon::create(2026, 9, 1)]);

    $response = $this->actingAs($user)
        ->get(route('fixed-costs.report-pdf', ['from' => '2026-09-01', 'to' => '2026-09-30']));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('gastos-2026-09-01_a_2026-09-30.pdf');

    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

test('el PDF sin logo no explota', function () {
    [$user, $tenant, $category] = reportSetup();
    expect($tenant->logo_path)->toBeNull();
    FixedCost::factory()->for($tenant)->for($category, 'category')->create();

    $response = $this->actingAs($user)->get(route('fixed-costs.report-pdf'));

    $response->assertOk();
    expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
});

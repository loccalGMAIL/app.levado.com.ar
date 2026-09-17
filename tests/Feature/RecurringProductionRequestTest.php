<?php

use App\Enums\TenantUserRole;
use App\Models\RecurringProductionRequest;
use Carbon\Carbon;

// occursOn() es lógica pura sobre atributos — sin persistir, para no pagar
// una consulta por caso del dataset. 2026-09-21 es lunes (ISO 1),
// 2026-09-20 y 2026-09-27 son domingo (ISO 7) — confirmado con `date`.

function recurringRequest(array $overrides = []): RecurringProductionRequest
{
    return new RecurringProductionRequest([
        'weekdays' => [1, 2, 3, 4, 5, 6], // lunes a sábado, el caso real
        'starts_on' => '2026-01-01',
        'ends_on' => null,
        ...$overrides,
    ]);
}

test('ocurre un lunes si lunes está en weekdays', function () {
    expect(recurringRequest()->occursOn(Carbon::parse('2026-09-21')))->toBeTrue();
});

test('no ocurre un domingo si domingo no está en weekdays', function () {
    expect(recurringRequest()->occursOn(Carbon::parse('2026-09-20')))->toBeFalse();
});

test('ocurre un domingo si domingo sí está en weekdays', function () {
    expect(recurringRequest(['weekdays' => [7]])->occursOn(Carbon::parse('2026-09-20')))->toBeTrue();
});

test('no ocurre antes de starts_on', function () {
    $recurring = recurringRequest(['starts_on' => '2026-09-25']);

    expect($recurring->occursOn(Carbon::parse('2026-09-21')))->toBeFalse(); // antes de starts_on
});

test('ocurre exactamente en starts_on', function () {
    $recurring = recurringRequest(['starts_on' => '2026-09-21']);

    expect($recurring->occursOn(Carbon::parse('2026-09-21')))->toBeTrue();
});

test('no ocurre después de ends_on', function () {
    $recurring = recurringRequest(['ends_on' => '2026-09-21']);

    expect($recurring->occursOn(Carbon::parse('2026-09-22')))->toBeFalse();
});

test('ocurre exactamente en ends_on', function () {
    $recurring = recurringRequest(['ends_on' => '2026-09-21']);

    expect($recurring->occursOn(Carbon::parse('2026-09-21')))->toBeTrue();
});

test('sin ends_on, ocurre indefinidamente hacia adelante', function () {
    $recurring = recurringRequest(['ends_on' => null]);

    expect($recurring->occursOn(Carbon::parse('2030-01-01')))->toBeTrue();
});

test('un recurrente inactivo se filtra por scopeActive, no por occursOn', function () {
    // active no participa de occursOn() a propósito: el materializador filtra
    // por scopeActive() antes de evaluar fechas — occursOn() sólo sabe de días.
    [, $tenant] = tenantUserAs(TenantUserRole::Owner);
    $active = RecurringProductionRequest::factory()->for($tenant)->create();
    $inactive = RecurringProductionRequest::factory()->for($tenant)->inactive()->create();

    expect(RecurringProductionRequest::active()->pluck('id')->all())->toBe([$active->id])
        ->and($inactive->occursOn(Carbon::parse('2026-09-21')))->toBeTrue(); // occursOn no mira active
});

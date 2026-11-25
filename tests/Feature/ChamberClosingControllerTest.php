<?php

use App\Enums\MobileShortcut;
use App\Enums\ProductionRecordStatus;
use App\Enums\TenantUserRole;
use App\Enums\WasteReason;
use App\Models\ChamberClosing;
use App\Services\ChamberClosingService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 08:00:00'));
});

test('owner cierra la cámara contando latas de a cuartos', function () {
    [$user, $tenant] = bakerySetup();
    $pan = doughProduct($tenant, 'Pan francés', tinPer: 30);
    seedChamber($pan, 90, $user);

    $this->actingAs($user)->post(route('produccion.camara.store'))->assertRedirect();
    $closing = ChamberClosing::query()->firstOrFail();

    $this->actingAs($user)
        ->get(route('produccion.camara.show', $closing))
        ->assertOk()
        ->assertSee('Confirmar cierre');

    $this->actingAs($user)
        ->put(route('produccion.camara.update', $closing), [
            'confirm' => 1,
            'lines' => [['product_id' => $pan->id, 'counted' => 0.5, 'waste_units' => 3, 'waste_reason' => WasteReason::BadlyStacked->value]],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('produccion.camara.show', $closing));

    // 90 − 0,5 lata × 30 − 3 = 72.
    expect($closing->fresh()->status)->toBe(ProductionRecordStatus::Confirmed)
        ->and($closing->fresh()->lines->first()->baked_units)->toBe(72.0)
        ->and(stockAt($pan, $tenant->defaultLocation()))->toBe(72.0)
        ->and(stockAt($pan, $tenant->proofingChamber()))->toBe(15.0);

    $this->actingAs($user)
        ->get(route('produccion.camara.show', $closing))
        ->assertOk()
        ->assertSee('Resumen')
        ->assertSee('Mal apilado');
});

test('las latas que no son de a cuartos vuelven con error', function () {
    [$user, $tenant] = bakerySetup();
    $pan = doughProduct($tenant, 'Pan francés', tinPer: 30);
    seedChamber($pan, 90, $user);
    $closing = app(ChamberClosingService::class)->open($tenant);

    $this->actingAs($user)
        ->put(route('produccion.camara.update', $closing), ['lines' => [['product_id' => $pan->id, 'counted' => 0.3]]])
        ->assertSessionHasErrors('lines.0.counted');
});

test('la merma de horno sin motivo vuelve con error', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    seedChamber($medialunas, 24, $user);
    $closing = app(ChamberClosingService::class)->open($tenant);

    $this->actingAs($user)
        ->put(route('produccion.camara.update', $closing), ['lines' => [['product_id' => $medialunas->id, 'counted' => 0, 'waste_units' => 2]]])
        ->assertSessionHasErrors('lines.0.waste_reason');
});

test('owner anula y descarta cierres', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    seedChamber($medialunas, 24, $user);
    $service = app(ChamberClosingService::class);
    $closing = $service->open($tenant);
    $service->confirm($closing, $user);

    $this->actingAs($user)->post(route('produccion.camara.cancel', $closing))->assertRedirect();
    expect($closing->fresh()->status)->toBe(ProductionRecordStatus::Cancelled);

    $draft = $service->open($tenant);
    $this->actingAs($user)->delete(route('produccion.camara.destroy', $draft))->assertRedirect(route('produccion.camara.index'));
    expect(ChamberClosing::query()->whereKey($draft->id)->exists())->toBeFalse();
});

test('un viewer ve los cierres pero no cierra', function () {
    [$user, $tenant] = bakerySetup(role: TenantUserRole::Viewer);

    $this->actingAs($user)->get(route('produccion.camara.index'))->assertOk()->assertDontSee('Cerrar cámara');
    $this->actingAs($user)->post(route('produccion.camara.store'))->assertForbidden();
});

test('no se ve el cierre de otro negocio', function () {
    [, $tenantA] = bakerySetup();
    [$userB] = tenantUserAs(TenantUserRole::Owner);
    $closing = app(ChamberClosingService::class)->open($tenantA);

    $this->actingAs($userB)->get(route('produccion.camara.show', $closing))->assertNotFound();
});

test('la planilla de amasado avisa si la cámara no se cerró hoy y muestra el último cierre', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    sheetOrder($tenant, $medialunas, [0, 0, 0, 24, 0, 0, 0], '2026-11-23');

    $this->actingAs($user)
        ->get(route('produccion.amasado.index'))
        ->assertOk()
        ->assertSee('La cámara no se cerró hoy');

    $service = app(ChamberClosingService::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-25 10:42:00'));
    $service->confirm($service->open($tenant), $user);

    $this->actingAs($user)
        ->get(route('produccion.amasado.index'))
        ->assertOk()
        ->assertDontSee('La cámara no se cerró hoy')
        ->assertSee('Último cierre de cámara: hoy 10:42');
});

test('cierre de cámara se puede elegir como acceso rápido mobile', function () {
    expect(MobileShortcut::ChamberClosing->routeName())->toBe('produccion.camara.index')
        ->and(MobileShortcut::ChamberClosing->group())->toBe('Producción');
});

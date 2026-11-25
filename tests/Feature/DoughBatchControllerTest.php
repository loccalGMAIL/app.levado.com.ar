<?php

use App\Enums\MobileShortcut;
use App\Enums\ProductionRecordStatus;
use App\Enums\TenantUserRole;
use App\Enums\WasteReason;
use App\Models\DoughBatch;
use App\Services\DoughBatchService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 08:00:00'));
});

test('owner genera el amasado del día desde la planilla y lo confirma', function () {
    [$user, $tenant, $medialunas, $harina] = bakerySetup();
    sheetOrder($tenant, $medialunas, [0, 0, 0, 24, 0, 0, 0], '2026-11-23');

    $this->actingAs($user)
        ->post(route('produccion.amasados.store'), ['fecha' => '2026-11-25'])
        ->assertRedirect();

    $batch = DoughBatch::query()->firstOrFail();

    $this->actingAs($user)
        ->get(route('produccion.amasados.show', $batch))
        ->assertOk()
        ->assertSee('Confirmar amasado')
        ->assertSee('Medialunas');

    $this->actingAs($user)
        ->put(route('produccion.amasados.update', $batch), [
            'confirm' => 1,
            'lines' => [['product_id' => $medialunas->id, 'actual_units' => 22, 'waste_units' => 2, 'waste_reason' => WasteReason::Proofing->value]],
        ])
        ->assertRedirect(route('produccion.amasados.show', $batch))
        ->assertSessionHasNoErrors();

    expect($batch->fresh()->status)->toBe(ProductionRecordStatus::Confirmed)
        ->and(stockAt($medialunas, $tenant->proofingChamber()))->toBe(22.0)
        ->and(stockAt($harina, $tenant->defaultLocation()))->toBe(-2400.0);

    $this->actingAs($user)
        ->get(route('produccion.amasados.show', $batch))
        ->assertOk()
        ->assertSee('Movimientos de stock')
        ->assertSee('Leudado')
        ->assertSee('Anular');
});

test('la merma sin motivo vuelve con error y no confirma', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    $batch = app(DoughBatchService::class)->createAdHoc($tenant, CarbonImmutable::parse('2026-11-25'));

    $this->actingAs($user)
        ->put(route('produccion.amasados.update', $batch), [
            'confirm' => 1,
            'lines' => [['product_id' => $medialunas->id, 'actual_units' => 22, 'waste_units' => 2]],
        ])
        ->assertSessionHasErrors('lines.0.waste_reason');

    expect($batch->fresh()->isDraft())->toBeTrue();
});

test('owner anula un amasado confirmado', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    $service = app(DoughBatchService::class);
    $batch = $service->createAdHoc($tenant, CarbonImmutable::parse('2026-11-25'));
    $service->saveDraft($batch, ['lines' => [['product_id' => $medialunas->id, 'actual_units' => 12]]]);
    $service->confirm($batch->fresh(), $user);

    $this->actingAs($user)
        ->post(route('produccion.amasados.cancel', $batch))
        ->assertRedirect(route('produccion.amasados.show', $batch));

    expect($batch->fresh()->status)->toBe(ProductionRecordStatus::Cancelled)
        ->and(stockAt($medialunas, $tenant->proofingChamber()))->toBe(0.0);
});

test('owner descarta un borrador', function () {
    [$user, $tenant] = bakerySetup();
    $batch = app(DoughBatchService::class)->createAdHoc($tenant, CarbonImmutable::parse('2026-11-25'));

    $this->actingAs($user)
        ->delete(route('produccion.amasados.destroy', $batch))
        ->assertRedirect(route('produccion.amasado.index', ['fecha' => '2026-11-25']));

    expect(DoughBatch::query()->count())->toBe(0)
        ->and(DoughBatch::withTrashed()->count())->toBe(1);
});

test('un viewer ve los amasados pero no registra', function () {
    [$user, $tenant] = bakerySetup(role: TenantUserRole::Viewer);
    $batch = app(DoughBatchService::class)->createAdHoc($tenant, CarbonImmutable::parse('2026-11-25'));

    $this->actingAs($user)->get(route('produccion.amasado.index'))->assertOk()->assertDontSee('Generar amasado');
    $this->actingAs($user)->get(route('produccion.amasados.show', $batch))->assertOk()->assertDontSee('Confirmar amasado');
    $this->actingAs($user)->post(route('produccion.amasados.store'), ['fecha' => '2026-11-25'])->assertForbidden();
    $this->actingAs($user)->put(route('produccion.amasados.update', $batch), ['lines' => []])->assertForbidden();
});

test('no se ve ni se toca el amasado de otro negocio', function () {
    [, $tenantA] = bakerySetup();
    [$userB] = tenantUserAs(TenantUserRole::Owner);
    $batch = app(DoughBatchService::class)->createAdHoc($tenantA, CarbonImmutable::parse('2026-11-25'));

    $this->actingAs($userB)->get(route('produccion.amasados.show', $batch))->assertNotFound();
    $this->actingAs($userB)->put(route('produccion.amasados.update', $batch), ['lines' => []])->assertNotFound();
});

test('el listado de amasado muestra los registrados y el botón para seguir el borrador', function () {
    [$user, $tenant] = bakerySetup();
    $batch = app(DoughBatchService::class)->createAdHoc($tenant, CarbonImmutable::parse('2026-11-25'));

    $this->actingAs($user)
        ->get(route('produccion.amasado.index', ['fecha' => '2026-11-25']))
        ->assertOk()
        ->assertSee('Seguir amasado #'.$batch->number)
        ->assertSee('Borrador');
});

test('amasado se puede elegir como acceso rápido mobile', function () {
    expect(MobileShortcut::Dough->routeName())->toBe('produccion.amasado.index')
        ->and(MobileShortcut::Dough->group())->toBe('Producción');
});

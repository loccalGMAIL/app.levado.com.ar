<?php

use App\Enums\ProductionRecordStatus;
use App\Enums\StockMovementType;
use App\Enums\WasteReason;
use App\Models\StockMovement;
use App\Services\ChamberClosingService;
use App\Services\DoughBatchService;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 08:00:00'));
});

function closings(): ChamberClosingService
{
    return app(ChamberClosingService::class);
}

/** Amasa y confirma $units del artículo (entran a cámara si pasa por cámara). */
function kneadAndConfirm($tenant, $product, float $units, $user): void
{
    $service = app(DoughBatchService::class);
    $batch = $service->createAdHoc($tenant, CarbonImmutable::now());
    $service->saveDraft($batch, ['lines' => [['product_id' => $product->id, 'actual_units' => $units]]]);
    $service->confirm($batch->fresh(), $user);
}

test('abrir el cierre congela lo que hay en cámara', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    kneadAndConfirm($tenant, $medialunas, 48, $user);

    $closing = closings()->open($tenant);

    expect($closing->lines)->toHaveCount(1)
        ->and($closing->lines->first()->expected_units)->toBe(48.0)
        ->and(closings()->open($tenant)->id)->toBe($closing->id);
});

test('horneado = esperado − contado − merma, traspasado al mismo costo', function () {
    [$user, $tenant, $medialunas, , $bolsa] = bakerySetup();
    kneadAndConfirm($tenant, $medialunas, 48, $user);
    $closing = closings()->open($tenant);

    closings()->saveDraft($closing, [[
        'product_id' => $medialunas->id, 'counted_units' => 6, 'waste_units' => 6, 'waste_reason' => WasteReason::Burnt->value,
    ]]);
    closings()->confirm($closing->fresh(), $user);

    $chamber = $tenant->proofingChamber();
    $store = $tenant->defaultLocation();
    $line = $closing->fresh()->lines->first();

    expect($line->baked_units)->toBe(36.0)
        ->and(stockAt($medialunas, $chamber))->toBe(6.0)
        ->and(stockAt($medialunas, $store))->toBe(36.0)
        // Descartables solo por lo horneado: 36 ÷ 12 × 12 bolsas.
        ->and(stockAt($bolsa, $store))->toBe(-36.0);

    $bakes = StockMovement::query()->where('type', StockMovementType::Bake->value)->where('stockable_type', 'product')->get();
    expect($bakes)->toHaveCount(2)
        ->and((float) $bakes->sum('quantity'))->toBe(0.0)
        ->and($bakes->pluck('unit_cost')->map(fn ($cost) => (float) $cost)->unique()->all())->toBe([6.0]);

    $waste = StockMovement::query()->where('type', StockMovementType::Waste->value)->firstOrFail();
    expect((float) $waste->quantity)->toBe(-6.0)
        ->and($waste->location_id)->toBe($chamber->id)
        ->and($waste->reason)->toContain('Quemado');
});

test('un amasado confirmado durante el cierre abierto no se toma como horneado', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    kneadAndConfirm($tenant, $medialunas, 48, $user);
    $closing = closings()->open($tenant);

    // Mientras el hornero cuenta, entra otro amasado a la cámara.
    kneadAndConfirm($tenant, $medialunas, 24, $user);

    closings()->saveDraft($closing, [['product_id' => $medialunas->id, 'counted_units' => 0]]);
    closings()->confirm($closing->fresh(), $user);

    expect(stockAt($medialunas, $tenant->defaultLocation()))->toBe(48.0)
        ->and(stockAt($medialunas, $tenant->proofingChamber()))->toBe(24.0);
});

test('contar más de lo esperado da horneado negativo y se acepta', function () {
    [$user, $tenant, $medialunas, , $bolsa] = bakerySetup();
    kneadAndConfirm($tenant, $medialunas, 12, $user);
    $closing = closings()->open($tenant);

    closings()->saveDraft($closing, [['product_id' => $medialunas->id, 'counted_units' => 15]]);
    closings()->confirm($closing->fresh(), $user);

    expect($closing->fresh()->lines->first()->baked_units)->toBe(-3.0)
        ->and(stockAt($medialunas, $tenant->proofingChamber()))->toBe(15.0)
        ->and(stockAt($medialunas, $tenant->defaultLocation()))->toBe(-3.0)
        ->and(stockAt($bolsa, $tenant->defaultLocation()))->toBe(0.0);
});

test('el traspaso usa el costo de la entrada a cámara aunque la receta cambie', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    kneadAndConfirm($tenant, $medialunas, 12, $user);
    $medialunas->recipe->update(['unit_cost' => 99]);
    $closing = closings()->open($tenant);

    closings()->saveDraft($closing, [['product_id' => $medialunas->id, 'counted_units' => 0]]);
    closings()->confirm($closing->fresh(), $user);

    $bake = StockMovement::query()->where('type', StockMovementType::Bake->value)->where('stockable_type', 'product')->firstOrFail();
    expect((float) $bake->unit_cost)->toBe(6.0)
        ->and($closing->fresh()->lines->first()->unit_cost)->toBe(6.0);
});

test('anular el cierre revierte todo y es idempotente', function () {
    [$user, $tenant, $medialunas, , $bolsa] = bakerySetup();
    kneadAndConfirm($tenant, $medialunas, 24, $user);
    $closing = closings()->open($tenant);
    closings()->saveDraft($closing, [['product_id' => $medialunas->id, 'counted_units' => 0, 'waste_units' => 2, 'waste_reason' => WasteReason::Crushed->value]]);
    closings()->confirm($closing->fresh(), $user);

    closings()->cancel($closing->fresh(), $user);
    closings()->cancel($closing->fresh(), $user);

    expect($closing->fresh()->status)->toBe(ProductionRecordStatus::Cancelled)
        ->and(stockAt($medialunas, $tenant->proofingChamber()))->toBe(24.0)
        ->and(stockAt($medialunas, $tenant->defaultLocation()))->toBe(0.0)
        ->and(stockAt($bolsa, $tenant->defaultLocation()))->toBe(0.0);
});

test('la merma de horno exige un motivo de horno', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    kneadAndConfirm($tenant, $medialunas, 24, $user);
    $closing = closings()->open($tenant);
    closings()->saveDraft($closing, [['product_id' => $medialunas->id, 'counted_units' => 0, 'waste_units' => 2, 'waste_reason' => WasteReason::Hydration->value]]);

    expect(fn () => closings()->confirm($closing->fresh(), $user))->toThrow(HttpException::class);
    expect($closing->fresh()->isDraft())->toBeTrue();
});

test('el cierre no consume insumos', function () {
    [$user, $tenant, $medialunas, $harina] = bakerySetup();
    kneadAndConfirm($tenant, $medialunas, 24, $user);
    $before = stockAt($harina, $tenant->defaultLocation());
    $closing = closings()->open($tenant);
    closings()->saveDraft($closing, [['product_id' => $medialunas->id, 'counted_units' => 0]]);

    closings()->confirm($closing->fresh(), $user);

    expect(stockAt($harina, $tenant->defaultLocation()))->toBe($before);
});

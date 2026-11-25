<?php

use App\Enums\ProductionRecordStatus;
use App\Enums\StockMovementType;
use App\Enums\TenantUserRole;
use App\Enums\Unit;
use App\Enums\WasteReason;
use App\Models\DoughBatch;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Services\DoughBatchService;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-25 08:00:00'));
});

function doughBatches(): DoughBatchService
{
    return app(DoughBatchService::class);
}

/** Borrador del día con un renglón cargado. */
function draftWith($tenant, $product, float $actual, float $waste = 0, ?WasteReason $reason = null, ?string $note = null): DoughBatch
{
    $batch = doughBatches()->createAdHoc($tenant, CarbonImmutable::parse('2026-11-25'));
    doughBatches()->saveDraft($batch, ['lines' => [[
        'product_id' => $product->id,
        'actual_units' => $actual,
        'waste_units' => $waste,
        'waste_reason' => $reason?->value,
        'waste_note' => $note,
    ]]]);

    return $batch->fresh();
}

test('confirmar un renglón con cámara consume insumos por real + merma y entra el crudo a cámara', function () {
    [$user, $tenant, $medialunas, $harina, $bolsa] = bakerySetup(proofing: true);
    $batch = draftWith($tenant, $medialunas, actual: 24, waste: 6, reason: WasteReason::Hydration);

    doughBatches()->confirm($batch, $user);

    $default = $tenant->defaultLocation();
    $chamber = $tenant->proofingChamber();
    // (24 + 6) ÷ 12 × 1.200 g: la merma también consumió harina.
    expect(stockAt($harina, $default))->toBe(-3000.0)
        ->and(stockAt($medialunas, $chamber))->toBe(24.0)
        ->and(stockAt($medialunas, $default))->toBe(0.0)
        // Los descartables se descuentan al hornear, no al amasar.
        ->and(stockAt($bolsa, $default))->toBe(0.0)
        ->and($batch->fresh()->status)->toBe(ProductionRecordStatus::Confirmed)
        ->and(StockMovement::query()->where('reference_type', 'dough_batch')->pluck('type')->unique()->all())->toBe([StockMovementType::Dough]);
});

test('un renglón directo entra como terminado y descuenta sus descartables por lo real', function () {
    [$user, $tenant, $galletitas, $harina, $bolsa] = bakerySetup(proofing: false);
    $batch = draftWith($tenant, $galletitas, actual: 12, waste: 12, reason: WasteReason::Misshaped);

    doughBatches()->confirm($batch, $user);

    $default = $tenant->defaultLocation();
    expect(stockAt($galletitas, $default))->toBe(12.0)
        ->and(stockAt($harina, $default))->toBe(-2400.0)
        ->and(stockAt($bolsa, $default))->toBe(-12.0)
        ->and($tenant->existingProofingChamber())->toBeNull();
});

test('las sub-recetas se consumen hasta sus insumos y sus descartables esperan al horno', function () {
    [$user, $tenant, $medialunas, $harina, $bolsa] = bakerySetup(proofing: true);
    $manteca = Ingredient::factory()->for($tenant)->create(['unit' => Unit::Gramo->value, 'cost_per_unit' => 0.02]);
    $film = Packaging::factory()->for($tenant)->create(['cost_per_unit' => 1]);
    $hojaldre = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_quantity' => 10, 'yield_unit' => Unit::Kilogramo->value]);
    $hojaldre->ingredientLines()->create(['ingredient_id' => $manteca->id, 'quantity' => 5000, 'unit' => Unit::Gramo->value]);
    $hojaldre->packagingLines()->create(['packaging_id' => $film->id, 'quantity' => 1]);
    $medialunas->recipe->subrecipeLines()->create(['child_recipe_id' => $hojaldre->id, 'quantity_used' => 2, 'unit' => Unit::Kilogramo->value]);

    doughBatches()->confirm(draftWith($tenant, $medialunas->fresh(['recipe']), actual: 24), $user);

    $default = $tenant->defaultLocation();
    // 24 ÷ 12 = 2 recetas → 4 kg de hojaldre → 2.000 g de manteca.
    expect(stockAt($manteca, $default))->toBe(-2000.0)
        ->and(stockAt($harina, $default))->toBe(-2400.0)
        ->and(stockAt($film, $default))->toBe(0.0)
        ->and(stockAt($bolsa, $default))->toBe(0.0);
});

test('el renglón guarda el costo unitario y la merma se valoriza con él', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    $batch = draftWith($tenant, $medialunas, actual: 24, waste: 2, reason: WasteReason::Proofing);

    doughBatches()->confirm($batch, $user);

    $line = $batch->fresh()->lines->first();
    // Receta: 1.200 g × 0,01 + 12 bolsas × 5 = 72 por 12 → 6 por unidad.
    expect($line->unit_cost)->toBe(6.0)
        ->and($line->wasteCost())->toBe(12.0)
        ->and($line->goes_to_chamber)->toBeTrue()
        ->and((float) StockMovement::query()->where('stockable_type', 'product')->value('unit_cost'))->toBe(6.0);
});

test('una merma sin motivo no se confirma', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    $batch = draftWith($tenant, $medialunas, actual: 24, waste: 2);

    expect(fn () => doughBatches()->confirm($batch, $user))->toThrow(HttpException::class);
    expect($batch->fresh()->isDraft())->toBeTrue()
        ->and(StockMovement::query()->count())->toBe(0);
});

test('una merma «Otro» exige el texto', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    $batch = draftWith($tenant, $medialunas, actual: 24, waste: 2, reason: WasteReason::DoughOther);

    expect(fn () => doughBatches()->confirm($batch, $user))->toThrow(HttpException::class);
});

test('un motivo de horno no vale para la merma del amasado', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    $batch = draftWith($tenant, $medialunas, actual: 24, waste: 2, reason: WasteReason::Burnt);

    expect(fn () => doughBatches()->confirm($batch, $user))->toThrow(HttpException::class);
});

test('anular revierte todo y es idempotente', function () {
    [$user, $tenant, $medialunas, $harina] = bakerySetup();
    $batch = draftWith($tenant, $medialunas, actual: 24);
    doughBatches()->confirm($batch, $user);

    expect(doughBatches()->cancel($batch->fresh(), $user))->toBe([]);
    doughBatches()->cancel($batch->fresh(), $user);

    expect(stockAt($harina, $tenant->defaultLocation()))->toBe(0.0)
        ->and(stockAt($medialunas, $tenant->proofingChamber()))->toBe(0.0)
        ->and($batch->fresh()->status)->toBe(ProductionRecordStatus::Cancelled)
        ->and(StockMovement::query()->whereNotNull('reverses_movement_id')->count())->toBe(2);
});

test('anular un amasado ya horneado deja la cámara en negativo y avisa', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    $batch = draftWith($tenant, $medialunas, actual: 24);
    doughBatches()->confirm($batch, $user);
    seedChamber($medialunas, -24, $user);

    $warnings = doughBatches()->cancel($batch->fresh(), $user);

    expect($warnings)->toBe(['Medialunas'])
        ->and(stockAt($medialunas, $tenant->proofingChamber()))->toBe(-24.0);
});

test('no se edita ni se vuelve a confirmar un amasado confirmado', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    $batch = draftWith($tenant, $medialunas, actual: 24);
    doughBatches()->confirm($batch, $user);

    expect(fn () => doughBatches()->saveDraft($batch->fresh(), ['lines' => []]))->toThrow(HttpException::class)
        ->and(fn () => doughBatches()->confirm($batch->fresh(), $user))->toThrow(HttpException::class);
});

test('generar desde la planilla toma lo planeado y reabre el borrador del día', function () {
    [$user, $tenant, $medialunas] = bakerySetup();
    sheetOrder($tenant, $medialunas, [0, 0, 0, 48, 0, 0, 0], '2026-11-23');
    seedChamber($medialunas, 12, $user);

    $batch = doughBatches()->createFromPlan($tenant, CarbonImmutable::parse('2026-11-25'));
    $again = doughBatches()->createFromPlan($tenant, CarbonImmutable::parse('2026-11-25'));

    $line = $batch->lines()->firstOrFail();
    expect($again->id)->toBe($batch->id)
        ->and($line->planned_units)->toBe(36.0)
        ->and($line->actual_units)->toBe(36.0)
        ->and($line->goes_to_chamber)->toBeTrue()
        ->and($batch->number)->toBe(1);
});

test('guardar el borrador conserva lo planeado y registra la masa', function () {
    [, $tenant, $medialunas] = bakerySetup();
    $hojaldre = Recipe::factory()->for($tenant)->semiElaborate()->create(['yield_quantity' => 10, 'yield_unit' => Unit::Kilogramo->value]);
    $medialunas->recipe->subrecipeLines()->create(['child_recipe_id' => $hojaldre->id, 'quantity_used' => 2, 'unit' => Unit::Kilogramo->value]);
    sheetOrder($tenant, $medialunas, [0, 0, 0, 24, 0, 0, 0], '2026-11-23');
    $batch = doughBatches()->createFromPlan($tenant, CarbonImmutable::parse('2026-11-25'));

    doughBatches()->saveDraft($batch, [
        'notes' => 'Se cortó la luz',
        'lines' => [['product_id' => $medialunas->id, 'actual_units' => 20]],
        'masses' => [['recipe_id' => $hojaldre->id, 'leftover_quantity' => -0.5]],
    ]);

    $batch->refresh();
    expect($batch->lines->first()->planned_units)->toBe(24.0)
        ->and($batch->lines->first()->actual_units)->toBe(20.0)
        ->and($batch->masses->first()->planned_quantity)->toBe(4.0)
        ->and($batch->masses->first()->leftover_quantity)->toBe(-0.5)
        ->and($batch->notes)->toBe('Se cortó la luz');
});

test('cada negocio numera sus amasados', function () {
    [, $tenantA] = tenantUserAs(TenantUserRole::Owner);
    [, $tenantB] = tenantUserAs(TenantUserRole::Owner);

    $a1 = doughBatches()->createAdHoc($tenantA, CarbonImmutable::parse('2026-11-25'));
    $a2 = doughBatches()->createAdHoc($tenantA, CarbonImmutable::parse('2026-11-26'));
    $b1 = doughBatches()->createAdHoc($tenantB, CarbonImmutable::parse('2026-11-25'));

    expect([$a1->number, $a2->number, $b1->number])->toBe([1, 2, 1]);
});

<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\ProductionRecordStatus;
use App\Enums\StockMovementType;
use App\Enums\WasteReason;
use App\Models\DoughBatch;
use App\Models\DoughBatchLine;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Registro del amasado. Es el ÚNICO lugar donde se consumen los insumos de
 * producción (sin doble conteo: el horneado mueve artículos, no insumos).
 *
 * Al confirmar, por renglón:
 *  - insumos SIN descartables por (real + merma) ÷ rinde: la merma también
 *    consumió masa;
 *  - si pasa por cámara: entra el crudo (+real) a la cámara; sus descartables
 *    se descuentan recién al hornear (cierre de cámara);
 *  - si va directo: entra el terminado (+real) a la sucursal y se descuentan
 *    sus descartables por real ÷ rinde: amasar y terminar es el mismo acto.
 *
 * La merma del amasado no mueve stock de producto (esas piezas nunca
 * entraron); queda en el renglón con su motivo. Las masas son solo registro.
 * Todo movimiento lleva `reference_type = 'dough_batch'` para poder anularlo
 * con StockService::reverseMovementsFor().
 */
class DoughBatchService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly RecipeExploder $exploder,
        private readonly DoughPlanner $planner,
    ) {}

    /**
     * Borrador del día con lo planeado por la planilla de amasado (lo real
     * arranca igual a lo planeado). Si ya hay un borrador ese día lo
     * devuelve en vez de duplicarlo.
     */
    public function createFromPlan(Tenant $tenant, CarbonImmutable $date): DoughBatch
    {
        $existing = $this->draftFor($tenant, $date);

        if ($existing !== null) {
            return $existing;
        }

        $kitchen = $tenant->defaultLocation();
        $plan = $this->planner->forDate($kitchen, $date);

        return DB::transaction(function () use ($tenant, $kitchen, $date, $plan) {
            $batch = $this->createBatch($tenant, $kitchen, $date);

            $rows = array_values(array_filter(
                array_merge($plan['chamber'], $plan['direct']),
                fn (array $row) => $row['to_make'] > 0,
            ));

            foreach ($rows as $position => $row) {
                $batch->lines()->create([
                    'product_id' => $row['product_id'],
                    'position' => $position,
                    'goes_to_chamber' => $row['goes_to_chamber'],
                    'planned_units' => $row['to_make'],
                    'actual_units' => $row['to_make'],
                ]);
            }

            foreach ($plan['masses'] as $mass) {
                $batch->masses()->create([
                    'recipe_id' => $mass['recipe_id'],
                    'planned_quantity' => $mass['quantity'],
                ]);
            }

            return $batch;
        });
    }

    /** Amasado manual, sin planilla: un borrador vacío del día. */
    public function createAdHoc(Tenant $tenant, CarbonImmutable $date): DoughBatch
    {
        return $this->createBatch($tenant, $tenant->defaultLocation(), $date);
    }

    /**
     * Guarda el borrador: renglones (real y merma), sobrante o faltante de
     * cada masa y notas. Lo planeado de un renglón se conserva; un artículo
     * agregado a mano arranca con planeado 0.
     *
     * @param  array{notes?: string|null, lines?: array<int, array{product_id: int|string, actual_units?: float|int|string|null, waste_units?: float|int|string|null, waste_reason?: string|null, waste_note?: string|null}>, masses?: array<int, array{recipe_id: int|string, leftover_quantity?: float|int|string|null}>}  $data
     */
    public function saveDraft(DoughBatch $batch, array $data): void
    {
        abort_unless($batch->isDraft(), 422, 'Solo se edita un amasado en borrador.');

        DB::transaction(function () use ($batch, $data) {
            $batch->load('lines', 'masses');
            $previous = $batch->lines->keyBy('product_id');
            $goesToChamber = Product::query()
                ->whereIn('id', array_map(fn (array $line) => (int) $line['product_id'], $data['lines'] ?? []))
                ->with('recipe')
                ->get()
                ->mapWithKeys(fn (Product $product) => [$product->id => $product->usesProofing()]);

            $batch->lines()->delete();

            foreach (array_values($data['lines'] ?? []) as $position => $line) {
                $productId = (int) $line['product_id'];
                $wasteUnits = max(0.0, (float) ($line['waste_units'] ?? 0));
                $reason = $wasteUnits > 0 ? WasteReason::tryFrom((string) ($line['waste_reason'] ?? '')) : null;

                $batch->lines()->create([
                    'product_id' => $productId,
                    'position' => $position,
                    'goes_to_chamber' => (bool) ($goesToChamber[$productId] ?? false),
                    'planned_units' => $previous->get($productId)->planned_units ?? 0,
                    'actual_units' => max(0.0, (float) ($line['actual_units'] ?? 0)),
                    'waste_units' => $wasteUnits,
                    'waste_reason' => $reason,
                    'waste_note' => $reason?->requiresNote() ? ($line['waste_note'] ?? null) : null,
                ]);
            }

            $leftovers = collect($data['masses'] ?? [])->mapWithKeys(fn (array $mass) => [(int) $mass['recipe_id'] => (float) ($mass['leftover_quantity'] ?? 0)]);

            foreach ($batch->masses as $mass) {
                if ($leftovers->has($mass->recipe_id)) {
                    $mass->update(['leftover_quantity' => $leftovers->get($mass->recipe_id)]);
                }
            }

            $batch->update(['notes' => $data['notes'] ?? $batch->notes]);
        });
    }

    /**
     * Confirma el amasado: consume los insumos y entra el crudo o el
     * terminado, en una transacción. Los movimientos se emiten ordenados por
     * ítem y ubicación para tomar los locks de stock_levels siempre en el
     * mismo orden. Los snapshots (va a cámara, costo unitario) se toman acá.
     */
    public function confirm(DoughBatch $batch, User $user): void
    {
        DB::transaction(function () use ($batch, $user) {
            $locked = DoughBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isDraft(), 422, 'El amasado ya no está en borrador.');

            $batch->load(['lines.product.recipe', 'kitchenLocation.tenant']);
            $kitchen = $batch->kitchenLocation;
            $reason = "Amasado #{$batch->number}";

            /** @var array<string, array{item: Ingredient|Packaging|Product, location: Location, quantity: float, unit_cost: float}> $movements */
            $movements = [];

            foreach ($batch->lines as $line) {
                $this->guardLine($line);
                $product = $line->product;
                $recipe = $product->recipe;
                $yield = (float) $recipe->yield_quantity;
                $goesToChamber = $product->usesProofing();
                $unitCost = $product->currentCost() ?? 0.0;

                $line->update(['goes_to_chamber' => $goesToChamber, 'unit_cost' => $unitCost]);

                if ($line->producedUnits() > 0) {
                    $stages = $this->exploder->explodeByStage($recipe, $line->producedUnits() / $yield);

                    foreach ($stages['supplies'] as $entry) {
                        $this->addMovement($movements, $entry['item'], $kitchen, -$entry['quantity']);
                    }
                }

                if ($line->actual_units <= 0) {
                    continue;
                }

                if ($goesToChamber) {
                    $this->addMovement($movements, $product, $batch->kitchenLocation->tenant->proofingChamber(), $line->actual_units, $unitCost);

                    continue;
                }

                $this->addMovement($movements, $product, $kitchen, $line->actual_units, $unitCost);

                foreach ($this->exploder->explodeByStage($recipe, $line->actual_units / $yield)['packaging'] as $entry) {
                    $this->addMovement($movements, $entry['item'], $kitchen, -$entry['quantity']);
                }
            }

            ksort($movements);

            foreach ($movements as $movement) {
                if (abs($movement['quantity']) < 0.0001) {
                    continue;
                }

                $this->stock->registerMovement(
                    item: $movement['item'],
                    location: $movement['location'],
                    type: StockMovementType::Dough,
                    quantity: $movement['quantity'],
                    unitCost: $movement['unit_cost'],
                    reason: $reason,
                    user: $user,
                    referenceType: DoughBatch::REFERENCE_TYPE,
                    referenceId: $batch->id,
                );
            }

            $batch->update([
                'status' => ProductionRecordStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => $user->id,
            ]);
        });
    }

    /**
     * Anula un amasado confirmado revirtiendo todos sus movimientos.
     * Idempotente: anular uno ya anulado no hace nada. Si el crudo ya se
     * horneó, la cámara queda en negativo (permitido): devuelve los nombres
     * de esos artículos para avisar.
     *
     * @return list<string>
     */
    public function cancel(DoughBatch $batch, User $user): array
    {
        return DB::transaction(function () use ($batch, $user) {
            $locked = DoughBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ProductionRecordStatus::Cancelled) {
                return [];
            }

            abort_unless($locked->isConfirmed(), 422, 'Un borrador no se anula: se borra.');

            $this->stock->reverseMovementsFor(DoughBatch::REFERENCE_TYPE, $batch->id, $user);

            $batch->update([
                'status' => ProductionRecordStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
            ]);

            return $this->negativeInChamber($batch);
        });
    }

    /** Borra un borrador (soft delete). Un confirmado no se borra: se anula. */
    public function deleteDraft(DoughBatch $batch): void
    {
        abort_unless($batch->isDraft(), 422, 'Solo se borra un amasado en borrador.');

        $batch->delete();
    }

    public function draftFor(Tenant $tenant, CarbonImmutable $date): ?DoughBatch
    {
        return DoughBatch::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', ProductionRecordStatus::Draft->value)
            ->whereDate('dough_date', $date->toDateString())
            ->orderBy('id')
            ->first();
    }

    private function createBatch(Tenant $tenant, Location $kitchen, CarbonImmutable $date): DoughBatch
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(fn () => DoughBatch::create([
                    'tenant_id' => $tenant->id,
                    'number' => $this->nextNumber($tenant),
                    'kitchen_location_id' => $kitchen->id,
                    'dough_date' => $date->toDateString(),
                    'status' => ProductionRecordStatus::Draft,
                ]));
            } catch (QueryException $exception) {
                // Dos altas simultáneas tomaron el mismo número: el unique frenó a una.
                if (++$attempts >= 3) {
                    throw $exception;
                }
            }
        }
    }

    private function nextNumber(Tenant $tenant): int
    {
        return (int) DoughBatch::withTrashed()->where('tenant_id', $tenant->id)->max('number') + 1;
    }

    private function guardLine(DoughBatchLine $line): void
    {
        $product = $line->product;

        abort_unless(
            $product->isManufactured() && $product->recipe !== null && (float) $product->recipe->yield_quantity > 0,
            422,
            "{$product->name} no tiene una receta con rendimiento: no se puede amasar.",
        );

        if ($line->waste_units > 0) {
            abort_unless($line->waste_reason?->stage() === WasteReason::STAGE_DOUGH, 422, "Falta el motivo de la merma de {$product->name}.");
            abort_if($line->waste_reason->requiresNote() && blank($line->waste_note), 422, "Contá el motivo de la merma de {$product->name}.");
        }
    }

    /**
     * Acumula un movimiento por ítem y ubicación. La clave ordena por tipo,
     * id y ubicación (orden de locks). Sin costo dado, se valúa al costo
     * vigente del ítem.
     *
     * @param  array<string, array{item: Ingredient|Packaging|Product, location: Location, quantity: float, unit_cost: float}>  $movements
     */
    private function addMovement(array &$movements, Ingredient|Packaging|Product $item, Location $location, float $quantity, ?float $unitCost = null): void
    {
        $key = sprintf('%s:%010d:%010d', CatalogItemType::for($item)->value, $item->id, $location->id);

        if (isset($movements[$key])) {
            $movements[$key]['quantity'] += $quantity;

            return;
        }

        $movements[$key] = [
            'item' => $item,
            'location' => $location,
            'quantity' => $quantity,
            'unit_cost' => $unitCost ?? $this->stock->unitCostOf($item),
        ];
    }

    /**
     * Artículos del amasado que quedaron con stock negativo en la cámara.
     *
     * @return list<string>
     */
    private function negativeInChamber(DoughBatch $batch): array
    {
        $batch->load(['lines.product', 'kitchenLocation.tenant']);
        $chamber = $batch->kitchenLocation->tenant->existingProofingChamber();
        $chamberLines = $batch->lines->filter(fn (DoughBatchLine $line) => $line->goes_to_chamber);

        if ($chamber === null || $chamberLines->isEmpty()) {
            return [];
        }

        $negative = StockLevel::query()
            ->where('location_id', $chamber->id)
            ->where('stockable_type', CatalogItemType::Product->value)
            ->whereIn('stockable_id', $chamberLines->pluck('product_id'))
            ->where('quantity', '<', 0)
            ->pluck('stockable_id')
            ->all();

        return $chamberLines
            ->filter(fn (DoughBatchLine $line) => in_array($line->product_id, $negative, true))
            ->map(fn (DoughBatchLine $line) => $line->product->name)
            ->values()
            ->all();
    }
}

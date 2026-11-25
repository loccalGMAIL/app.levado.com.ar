<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\ProductionRecordStatus;
use App\Enums\StockMovementType;
use App\Enums\WasteReason;
use App\Models\ChamberClosing;
use App\Models\ChamberClosingLine;
use App\Models\DoughBatch;
use App\Models\Location;
use App\Models\Packaging;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Concerns\NumbersPerTenant;
use Illuminate\Support\Facades\DB;

/**
 * Horneado por cierre de cámara. El hornero cuenta lo que quedó en cámara y
 * la diferencia con lo esperado (menos la merma de horno) es lo horneado:
 *  - `Bake` −horneado en la cámara y `Bake` +horneado en la sucursal, al
 *    mismo costo unitario (traspaso puro, suma cero);
 *  - `Waste` −merma en la cámara, con su motivo;
 *  - los descartables de lo horneado (no de lo quemado) salen de la sucursal.
 *
 * No consume insumos: eso pasó al amasar. Lo esperado es una foto tomada al
 * abrir el cierre, así que un amasado confirmado mientras se cuenta no se
 * toma como horneado. Todo movimiento lleva `reference_type =
 * 'chamber_closing'` para poder anularlo.
 */
class ChamberClosingService
{
    use NumbersPerTenant;

    public function __construct(
        private readonly StockService $stock,
        private readonly RecipeExploder $exploder,
    ) {}

    /**
     * Abre un cierre con un renglón por artículo con stock en cámara distinto
     * de 0, congelando lo esperado. Si ya hay uno abierto lo devuelve.
     */
    public function open(Tenant $tenant): ChamberClosing
    {
        $existing = $this->openFor($tenant);

        if ($existing !== null) {
            return $existing;
        }

        $chamber = $tenant->proofingChamber();

        return DB::transaction(function () use ($tenant, $chamber) {
            $closing = $this->createNumbered(ChamberClosing::class, [
                'tenant_id' => $tenant->id,
                'chamber_location_id' => $chamber->id,
                'status' => ProductionRecordStatus::Draft,
                'opened_at' => now(),
            ]);

            $levels = StockLevel::query()
                ->where('tenant_id', $tenant->id)
                ->where('location_id', $chamber->id)
                ->where('stockable_type', CatalogItemType::Product->value)
                ->where('quantity', '!=', 0)
                ->get();

            $names = Product::query()->whereIn('id', $levels->pluck('stockable_id'))->pluck('name', 'id');

            foreach ($levels->sortBy(fn (StockLevel $level) => mb_strtolower((string) ($names[$level->stockable_id] ?? ''))) as $level) {
                $closing->lines()->create([
                    'product_id' => $level->stockable_id,
                    'expected_units' => (float) $level->quantity,
                ]);
            }

            return $closing;
        });
    }

    /**
     * Guarda lo contado y la merma del borrador, en unidades.
     *
     * @param  array<int, array{product_id: int|string, counted_units?: float|int|string|null, waste_units?: float|int|string|null, waste_reason?: string|null, waste_note?: string|null}>  $lines
     */
    public function saveDraft(ChamberClosing $closing, array $lines): void
    {
        abort_unless($closing->isDraft(), 422, 'Solo se edita un cierre abierto.');

        $byProduct = collect($lines)->keyBy(fn (array $line) => (int) $line['product_id']);

        DB::transaction(function () use ($closing, $byProduct) {
            foreach ($closing->lines as $line) {
                $input = $byProduct->get($line->product_id);

                if ($input === null) {
                    continue;
                }

                $waste = max(0.0, (float) ($input['waste_units'] ?? 0));
                $reason = $waste > 0 ? WasteReason::tryFrom((string) ($input['waste_reason'] ?? '')) : null;

                $line->update([
                    'counted_units' => max(0.0, (float) ($input['counted_units'] ?? 0)),
                    'waste_units' => $waste,
                    'waste_reason' => $reason,
                    'waste_note' => $reason?->requiresNote() ? ($input['waste_note'] ?? null) : null,
                ]);
            }
        });
    }

    /**
     * Confirma el cierre: traspasa lo horneado a terminado, saca la merma de
     * la cámara y descuenta los descartables de lo horneado.
     */
    public function confirm(ChamberClosing $closing, User $user): void
    {
        DB::transaction(function () use ($closing, $user) {
            $locked = ChamberClosing::query()->whereKey($closing->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isDraft(), 422, 'El cierre ya no está abierto.');

            $closing->load(['lines.product.recipe', 'chamberLocation.tenant']);
            $chamber = $closing->chamberLocation;
            $store = $chamber->tenant->defaultLocation();
            $reason = "Cierre de cámara #{$closing->number}";

            /** @var array<int, array{item: Packaging, quantity: float}> $packaging */
            $packaging = [];

            foreach ($closing->lines as $line) {
                $this->guardLine($line);
                $product = $line->product;
                $baked = $line->computedBakedUnits();
                $unitCost = $this->chamberEntryCost($product, $chamber->id);

                $line->update(['baked_units' => $baked, 'unit_cost' => $unitCost]);

                if (abs($baked) >= 0.0001) {
                    $this->move($product, $chamber, StockMovementType::Bake, -$baked, $unitCost, $reason, $user, $closing);
                    $this->move($product, $store, StockMovementType::Bake, $baked, $unitCost, $reason, $user, $closing);
                }

                if ($line->waste_units > 0) {
                    $wasteReason = $line->waste_reason?->describe($line->waste_note) ?? 'Merma';
                    $this->move($product, $chamber, StockMovementType::Waste, -$line->waste_units, $unitCost, "{$wasteReason} — {$reason}", $user, $closing);
                }

                $recipe = $product->recipe;

                if ($baked > 0 && $recipe !== null && (float) $recipe->yield_quantity > 0) {
                    foreach ($this->exploder->explodeByStage($recipe, $baked / (float) $recipe->yield_quantity)['packaging'] as $entry) {
                        $item = $entry['item'];
                        assert($item instanceof Packaging);
                        $packaging[$item->id] ??= ['item' => $item, 'quantity' => 0.0];
                        $packaging[$item->id]['quantity'] += $entry['quantity'];
                    }
                }
            }

            ksort($packaging);

            foreach ($packaging as $entry) {
                $this->move($entry['item'], $store, StockMovementType::Bake, -$entry['quantity'], $this->stock->unitCostOf($entry['item']), $reason, $user, $closing);
            }

            $closing->update([
                'status' => ProductionRecordStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => $user->id,
            ]);
        });
    }

    /** Anula un cierre confirmado revirtiendo sus movimientos. Idempotente. */
    public function cancel(ChamberClosing $closing, User $user): void
    {
        DB::transaction(function () use ($closing, $user) {
            $locked = ChamberClosing::query()->whereKey($closing->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ProductionRecordStatus::Cancelled) {
                return;
            }

            abort_unless($locked->isConfirmed(), 422, 'Un cierre abierto no se anula: se descarta.');

            $this->stock->reverseMovementsFor(ChamberClosing::REFERENCE_TYPE, $closing->id, $user);

            $closing->update([
                'status' => ProductionRecordStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
            ]);
        });
    }

    public function deleteDraft(ChamberClosing $closing): void
    {
        abort_unless($closing->isDraft(), 422, 'Solo se descarta un cierre abierto.');

        $closing->delete();
    }

    public function openFor(Tenant $tenant): ?ChamberClosing
    {
        return ChamberClosing::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', ProductionRecordStatus::Draft->value)
            ->orderBy('id')
            ->first();
    }

    /** El último cierre confirmado del negocio, o null si nunca se cerró. */
    public function lastConfirmed(Tenant $tenant): ?ChamberClosing
    {
        return ChamberClosing::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', ProductionRecordStatus::Confirmed->value)
            ->latest('confirmed_at')
            ->first();
    }

    private function guardLine(ChamberClosingLine $line): void
    {
        if ($line->waste_units <= 0) {
            return;
        }

        $name = $line->product->name;
        abort_unless($line->waste_reason?->stage() === WasteReason::STAGE_OVEN, 422, "Falta el motivo de la merma de {$name}.");
        abort_if($line->waste_reason->requiresNote() && blank($line->waste_note), 422, "Contá el motivo de la merma de {$name}.");
    }

    /**
     * Costo del crudo en cámara: el de su última entrada por amasado vigente
     * (no el actual de la receta, que pudo cambiar desde que se amasó). Sin
     * entradas, el costo vigente del artículo.
     */
    private function chamberEntryCost(Product $product, int $chamberId): float
    {
        $cost = StockMovement::query()
            ->where('location_id', $chamberId)
            ->where('stockable_type', CatalogItemType::Product->value)
            ->where('stockable_id', $product->id)
            ->where('reference_type', DoughBatch::REFERENCE_TYPE)
            ->where('quantity', '>', 0)
            ->whereNull('reverses_movement_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('stock_movements as reversals')
                    ->whereColumn('reversals.reverses_movement_id', 'stock_movements.id');
            })
            ->latest('id')
            ->value('unit_cost');

        return $cost !== null ? (float) $cost : ($product->currentCost() ?? 0.0);
    }

    private function move(Product|Packaging $item, Location $location, StockMovementType $type, float $quantity, float $unitCost, string $reason, User $user, ChamberClosing $closing): void
    {
        $this->stock->registerMovement(
            item: $item,
            location: $location,
            type: $type,
            quantity: $quantity,
            unitCost: $unitCost,
            reason: $reason,
            user: $user,
            referenceType: ChamberClosing::REFERENCE_TYPE,
            referenceId: $closing->id,
        );
    }
}

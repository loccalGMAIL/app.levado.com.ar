<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\StockMovementType;
use App\Models\CreditNoteLine;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Packaging;
use App\Models\PurchaseLine;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura del stock: nada inserta en stock_movements ni
 * actualiza stock_levels por fuera de este servicio.
 *
 * Las cantidades se expresan siempre en la unidad del ítem (ingredient.unit,
 * o sub-unidades cuando hay subdivisions; packaging siempre en unidades),
 * la misma unidad en la que está expresado cost_per_unit. La conversión desde
 * la unidad de compra ocurre en el borde (PurchaseLineRecorder + UnitConverter).
 *
 * El stock negativo está permitido: nunca se bloquea una salida, la alerta
 * es solo visual (StockLevel::hasAlert()).
 */
class StockService
{
    private const QUANTITY_TOLERANCE = 0.0001;

    /**
     * Registra un movimiento en el ledger y actualiza el cache stock_levels,
     * con lock pesimista sobre la fila del cache para serializar escrituras
     * concurrentes del mismo ítem/sucursal.
     */
    public function registerMovement(
        Ingredient|Packaging $item,
        Location $location,
        StockMovementType $type,
        float $quantity,
        float $unitCost,
        ?string $reason = null,
        ?User $user = null,
        PurchaseLine|CreditNoteLine|null $reference = null,
        ?StockMovement $reverses = null,
    ): StockMovement {
        abort_unless(! $type->requiresReason() || filled($reason), 422, 'El motivo es obligatorio para ajustes.');
        abort_unless($location->tenant_id === $item->tenant_id, 422, 'La sucursal no pertenece al tenant del ítem.');

        return DB::transaction(function () use ($item, $location, $type, $quantity, $unitCost, $reason, $user, $reference, $reverses) {
            $level = $this->lockedLevelRow($item, $location);

            $movement = StockMovement::create([
                'tenant_id' => $item->tenant_id,
                'location_id' => $location->id,
                'stockable_type' => $this->typeFor($item),
                'stockable_id' => $item->id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reason' => $reason,
                'reference_type' => $this->referenceTypeFor($reference),
                'reference_id' => $reference?->id,
                'reverses_movement_id' => $reverses?->id,
                'user_id' => $user?->id,
            ]);

            $updates = ['quantity' => (float) $level->quantity + $quantity];

            // El último costo de compra solo lo pisan las entradas reales de compra,
            // no los contramovimientos, los movimientos manuales ni las bonificaciones
            // (un obsequio no dice nada sobre lo que cuesta reponer el ítem).
            if ($type === StockMovementType::Purchase && $reverses === null) {
                $updates['unit_cost'] = $unitCost;
            }

            $level->update($updates);

            return $movement;
        });
    }

    /**
     * Recuento físico: registra la diferencia entre lo contado y el cache.
     * Devuelve null si no hay diferencia.
     */
    public function applyCount(Ingredient|Packaging $item, Location $location, float $countedQuantity, User $user): ?StockMovement
    {
        $current = (float) ($this->levelFor($item, $location)?->quantity ?? 0);
        $delta = $countedQuantity - $current;

        if (abs($delta) < self::QUANTITY_TOLERANCE) {
            return null;
        }

        return $this->registerMovement(
            item: $item,
            location: $location,
            type: StockMovementType::Count,
            quantity: $delta,
            unitCost: (float) $item->cost_per_unit,
            reason: 'Recuento físico '.now()->format('d/m/Y'),
            user: $user,
        );
    }

    /**
     * Ajuste manual con signo (+ entrada / − salida), valuado al costo actual del ítem.
     */
    public function registerAdjustment(Ingredient|Packaging $item, Location $location, float $signedQuantity, string $reason, User $user): StockMovement
    {
        return $this->registerMovement(
            item: $item,
            location: $location,
            type: StockMovementType::Adjustment,
            quantity: $signedQuantity,
            unitCost: (float) $item->cost_per_unit,
            reason: $reason,
            user: $user,
        );
    }

    /**
     * Entrada de stock por línea de compra aplicada, en la sucursal default del tenant.
     *
     * Idempotente: si la línea ya tiene una entrada activa idéntica (mismo ítem,
     * tipo, cantidad y costo) no hace nada. Si difiere (cambió cantidad/precio, se
     * re-asoció a otro ítem, o pasó de compra a bonificación), revierte la entrada
     * anterior y registra la nueva.
     *
     * $type distingue la compra normal de la bonificación (renglón sin cargo): la
     * bonificación entra al stock igual, pero no pisa el último costo del cache.
     */
    public function syncPurchaseLineEntry(
        PurchaseLine $line,
        Ingredient|Packaging $item,
        float $quantityInItemUnits,
        float $unitCost,
        ?User $user = null,
        StockMovementType $type = StockMovementType::Purchase,
    ): ?StockMovement {
        $active = $this->activePurchaseEntryFor($line);

        if ($active !== null) {
            $sameStockable = $active->stockable_type === $this->typeFor($item) && $active->stockable_id === $item->id;
            $sameAmounts = abs((float) $active->quantity - $quantityInItemUnits) < self::QUANTITY_TOLERANCE
                && abs((float) $active->unit_cost - $unitCost) < self::QUANTITY_TOLERANCE;

            if ($sameStockable && $sameAmounts && $active->type === $type) {
                return null;
            }

            $this->reverseMovement($active, $user);
        }

        return $this->registerMovement(
            item: $item,
            location: $item->tenant->defaultLocation(),
            type: $type,
            quantity: $quantityInItemUnits,
            unitCost: $unitCost,
            user: $user,
            reference: $line,
        );
    }

    /**
     * Revierte la entrada activa de una línea de compra (contramovimiento exacto).
     * No-op si la línea no tiene entrada activa.
     */
    public function reversePurchaseLineEntry(PurchaseLine $line, ?User $user = null): ?StockMovement
    {
        $active = $this->activePurchaseEntryFor($line);

        if ($active === null) {
            return null;
        }

        return $this->reverseMovement($active, $user);
    }

    /**
     * Salida de stock por línea de nota de crédito aplicada (devolución de
     * mercadería), en la sucursal default del tenant. No es un contramovimiento
     * de la entrada de compra: reverses_movement_id queda null a propósito, para
     * que activePurchaseEntryFor() siga viendo la entrada original intacta y una
     * edición posterior del renglón de compra no duplique stock.
     *
     * Idempotente igual que syncPurchaseLineEntry(): si la línea ya tiene una
     * salida activa idéntica no hace nada; si difiere, la revierte y registra
     * la nueva.
     */
    public function syncCreditNoteLineExit(
        CreditNoteLine $line,
        Ingredient|Packaging $item,
        float $quantityInItemUnits,
        float $unitCost,
        ?User $user = null,
    ): ?StockMovement {
        $active = $this->activeCreditNoteExitFor($line);

        if ($active !== null) {
            $sameStockable = $active->stockable_type === $this->typeFor($item) && $active->stockable_id === $item->id;
            $sameAmounts = abs((float) $active->quantity - (-$quantityInItemUnits)) < self::QUANTITY_TOLERANCE
                && abs((float) $active->unit_cost - $unitCost) < self::QUANTITY_TOLERANCE;

            if ($sameStockable && $sameAmounts) {
                return null;
            }

            $this->reverseMovement($active, $user);
        }

        return $this->registerMovement(
            item: $item,
            location: $item->tenant->defaultLocation(),
            type: StockMovementType::Return,
            quantity: -$quantityInItemUnits,
            unitCost: $unitCost,
            user: $user,
            reference: $line,
        );
    }

    /**
     * Revierte la salida activa de una línea de nota de crédito (contramovimiento
     * exacto). No-op si la línea no tiene salida activa.
     */
    public function reverseCreditNoteLineExit(CreditNoteLine $line, ?User $user = null): ?StockMovement
    {
        $active = $this->activeCreditNoteExitFor($line);

        if ($active === null) {
            return null;
        }

        return $this->reverseMovement($active, $user);
    }

    public function setMinQuantity(Ingredient|Packaging $item, Location $location, ?float $minQuantity): void
    {
        DB::transaction(function () use ($item, $location, $minQuantity) {
            $this->lockedLevelRow($item, $location)->update(['min_quantity' => $minQuantity]);
        });
    }

    public function levelFor(Ingredient|Packaging $item, Location $location): ?StockLevel
    {
        return StockLevel::query()
            ->where('tenant_id', $item->tenant_id)
            ->where('stockable_type', $this->typeFor($item))
            ->where('stockable_id', $item->id)
            ->where('location_id', $location->id)
            ->first();
    }

    /**
     * Contramovimiento exacto: misma cantidad negada y mismo costo snapshot
     * del movimiento original, apuntándolo vía reverses_movement_id.
     */
    private function reverseMovement(StockMovement $original, ?User $user): StockMovement
    {
        $item = $original->stockable;
        abort_unless($item !== null, 422, 'El ítem del movimiento a revertir ya no existe.');

        return $this->registerMovement(
            item: $item,
            location: $original->location,
            type: $original->type,
            quantity: -(float) $original->quantity,
            unitCost: (float) $original->unit_cost,
            reason: $original->reason,
            user: $user,
            reverses: $original,
        );
    }

    /**
     * Entrada vigente de una línea de compra: movimiento de compra o bonificación
     * referenciado a la línea, que no es contramovimiento y no fue revertido por
     * otro movimiento. Contempla los dos tipos para que un renglón que pasa de
     * compra a bonificación (o al revés) revierta su entrada anterior en vez de
     * duplicarla.
     *
     * Pública: CreditNoteLineRecorder la usa para derivar la cantidad y el costo
     * de una devolución proporcional a la entrada original, sin repetir la
     * conversión de unidades de PurchaseLineRecorder.
     */
    public function activePurchaseEntryFor(PurchaseLine $line): ?StockMovement
    {
        return StockMovement::query()
            ->where('reference_type', 'purchase_line')
            ->where('reference_id', $line->id)
            ->whereIn('type', [StockMovementType::Purchase->value, StockMovementType::Bonus->value])
            ->whereNull('reverses_movement_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('stock_movements as reversals')
                    ->whereColumn('reversals.reverses_movement_id', 'stock_movements.id');
            })
            ->latest('id')
            ->first();
    }

    /**
     * Salida vigente de una línea de nota de crédito: movimiento de devolución
     * referenciado a la línea que no fue revertido por otro movimiento.
     */
    private function activeCreditNoteExitFor(CreditNoteLine $line): ?StockMovement
    {
        return StockMovement::query()
            ->where('reference_type', 'credit_note_line')
            ->where('reference_id', $line->id)
            ->where('type', StockMovementType::Return->value)
            ->whereNull('reverses_movement_id')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('stock_movements as reversals')
                    ->whereColumn('reversals.reverses_movement_id', 'stock_movements.id');
            })
            ->latest('id')
            ->first();
    }

    private function referenceTypeFor(PurchaseLine|CreditNoteLine|null $reference): ?string
    {
        return match (true) {
            $reference instanceof PurchaseLine => 'purchase_line',
            $reference instanceof CreditNoteLine => 'credit_note_line',
            default => null,
        };
    }

    /**
     * Fila del cache con lock pesimista, creándola en cero si no existe.
     * Ante una carrera en la creación (violación del unique), reintenta el lock.
     */
    private function lockedLevelRow(Ingredient|Packaging $item, Location $location): StockLevel
    {
        $attributes = [
            'tenant_id' => $item->tenant_id,
            'stockable_type' => $this->typeFor($item),
            'stockable_id' => $item->id,
            'location_id' => $location->id,
        ];

        $level = StockLevel::query()->where($attributes)->lockForUpdate()->first();

        if ($level !== null) {
            return $level;
        }

        try {
            return StockLevel::create($attributes + ['quantity' => 0, 'unit_cost' => 0]);
        } catch (QueryException) {
            return StockLevel::query()->where($attributes)->lockForUpdate()->firstOrFail();
        }
    }

    private function typeFor(Ingredient|Packaging $item): string
    {
        return CatalogItemType::for($item)->value;
    }
}

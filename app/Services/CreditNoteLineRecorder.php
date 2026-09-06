<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\CreditNoteLine;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Registra renglones de nota de crédito y, cuando corresponde, la salida de
 * stock que devuelven.
 *
 * A diferencia de PurchaseLineRecorder, este servicio NUNCA toca el costo del
 * insumo: ni price log, ni cost_per_unit, ni propagación a recetas. Una
 * devolución (mercadería que no vino, un reconocimiento por rotura) no dice
 * nada sobre lo que cuesta reponer el ítem — misma invariante que ya rige al
 * desasociar un renglón de compra.
 *
 * La cantidad devuelta se expresa en la unidad de compra del renglón de
 * origen, y la salida de stock se deriva PROPORCIONAL a la entrada vigente
 * de ese renglón (no repite la conversión de unidades de PurchaseLineRecorder):
 *
 *   salida = entradaVigente.quantity × (nc.quantity ÷ purchaseLine.quantity_purchased)
 *   unitCost = entradaVigente.unit_cost   (snapshot, no el costo de hoy)
 *
 * Una devolución total deja el neto de esa entrada exactamente en cero.
 */
class CreditNoteLineRecorder
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array{purchase_line_id?: int|string|null, description?: ?string, quantity: float|string, unit: string, unit_price: float|string, iva_rate?: float|string|null, affects_stock?: bool}  $data
     */
    public function storeLine(CreditNote $note, array $data, ?User $user = null): CreditNoteLine
    {
        return DB::transaction(function () use ($note, $data, $user) {
            $line = $note->lines()->create($this->attributesFor($data));

            if ($line->affectsStock()) {
                $this->applyStock($line, $user);
            }

            return $line;
        });
    }

    /**
     * @param  array{purchase_line_id?: int|string|null, description?: ?string, quantity: float|string, unit: string, unit_price: float|string, iva_rate?: float|string|null, affects_stock?: bool}  $data
     */
    public function recompute(CreditNoteLine $line, array $data, ?User $user = null): void
    {
        DB::transaction(function () use ($line, $data, $user) {
            $wasStockApplied = $line->isStockApplied();

            $line->update($this->attributesFor($data));
            $line->refresh();

            if ($line->affectsStock()) {
                $this->applyStock($line, $user);
            } elseif ($wasStockApplied) {
                $this->reverseStock($line, $user);
            }
        });
    }

    /**
     * Aplica (o re-sincroniza) la salida de stock del renglón. No-op si el
     * renglón no descuenta stock o no está atado a un renglón de compra.
     */
    public function applyStock(CreditNoteLine $line, ?User $user = null): ?StockMovement
    {
        if (! $line->affectsStock()) {
            return null;
        }

        $purchaseLine = $line->purchaseLine;
        abort_unless($purchaseLine !== null, 422, 'El renglón de compra de origen ya no existe.');
        abort_unless($purchaseLine->isApplied(), 422, 'No se puede devolver un renglón que todavía no fue aplicado.');
        abort_unless(! $purchaseLine->isExcluded(), 422, 'No se puede devolver un renglón marcado como "no es un insumo".');

        $entry = $this->stock->activePurchaseEntryFor($purchaseLine);
        abort_unless($entry !== null, 422, 'El renglón de compra de origen no tiene una entrada de stock vigente.');

        abort_if(
            (float) $line->quantity > (float) $purchaseLine->quantity_purchased + 0.0001,
            422,
            'La cantidad devuelta no puede superar la cantidad comprada.',
        );

        $proportion = (float) $line->quantity / (float) $purchaseLine->quantity_purchased;
        $quantityInItemUnits = abs((float) $entry->quantity) * $proportion;

        $item = $entry->stockable;
        abort_unless($item !== null, 422, 'El ítem de la compra de origen ya no existe.');

        $movement = $this->stock->syncCreditNoteLineExit(
            line: $line,
            item: $item,
            quantityInItemUnits: $quantityInItemUnits,
            unitCost: (float) $entry->unit_cost,
            user: $user,
        );

        $line->update(['stock_applied_at' => now()]);

        return $movement;
    }

    public function reverseStock(CreditNoteLine $line, ?User $user = null): void
    {
        $this->stock->reverseCreditNoteLineExit($line, $user);
        $line->update(['stock_applied_at' => null]);
    }

    /**
     * @param  array{purchase_line_id?: int|string|null, description?: ?string, quantity: float|string, unit: string, unit_price: float|string, iva_rate?: float|string|null, affects_stock?: bool}  $data
     * @return array<string, mixed>
     */
    private function attributesFor(array $data): array
    {
        $quantity = (float) $data['quantity'];
        $unitPrice = (float) $data['unit_price'];

        return [
            'purchase_line_id' => $data['purchase_line_id'] ?? null,
            'description' => $data['description'] ?? null,
            'quantity' => $quantity,
            'unit' => $data['unit'],
            'unit_price' => $unitPrice,
            'iva_rate' => $data['iva_rate'] ?? 0.21,
            'subtotal' => $quantity * $unitPrice,
            'affects_stock' => array_key_exists('affects_stock', $data)
                ? (bool) $data['affects_stock']
                : ! empty($data['purchase_line_id']),
        ];
    }
}

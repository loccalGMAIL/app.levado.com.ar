<?php

namespace App\Services;

use App\Enums\DiscountKind;
use App\Models\Purchase;
use App\Models\PurchaseDiscount;

/**
 * Alta, edición y recálculo de los descuentos de una compra.
 *
 * El descuento se aplica antes del IVA y sólo afecta el total de la factura:
 * este servicio nunca toca costos ni stock. Un descuento porcentual se
 * recalcula cuando cambian los renglones; uno por monto es fijo, para que
 * la factura cierre contra el papel.
 */
class PurchaseDiscountSync
{
    /**
     * @param  array{purchase_line_id?: int|string|null, description?: ?string, kind: string, rate?: float|string|null, amount?: float|string|null}  $data
     */
    public function store(Purchase $purchase, array $data): PurchaseDiscount
    {
        $discount = $purchase->discounts()->make($this->attributes($purchase, $data));
        $discount->save();

        return $discount;
    }

    /**
     * @param  array{purchase_line_id?: int|string|null, description?: ?string, kind: string, rate?: float|string|null, amount?: float|string|null}  $data
     */
    public function update(PurchaseDiscount $discount, array $data): PurchaseDiscount
    {
        $discount->update($this->attributes($discount->purchase, $data));

        return $discount;
    }

    /**
     * Recalcula importe (si es porcentual) e IVA de todos los descuentos de la
     * compra. Se llama cada vez que cambian sus renglones.
     */
    public function refresh(Purchase $purchase): void
    {
        $purchase->discounts()->get()->each(function (PurchaseDiscount $discount) use ($purchase) {
            $amount = $discount->kind === DiscountKind::Percent
                ? $this->percentAmount($purchase, $discount->purchase_line_id, (float) $discount->rate)
                : (float) $discount->amount;

            $discount->update([
                'amount' => $amount,
                'iva_amount' => $this->ivaFor($purchase, $discount->purchase_line_id, $amount),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Purchase $purchase, array $data): array
    {
        $kind = DiscountKind::from((string) $data['kind']);
        $lineId = filled($data['purchase_line_id'] ?? null) ? (int) $data['purchase_line_id'] : null;
        $rate = $kind === DiscountKind::Percent ? (float) ($data['rate'] ?? 0) : null;

        $amount = $kind === DiscountKind::Percent
            ? $this->percentAmount($purchase, $lineId, (float) $rate)
            : round((float) ($data['amount'] ?? 0), 2);

        return [
            'purchase_line_id' => $lineId,
            'description' => filled($data['description'] ?? null) ? $data['description'] : 'Descuento',
            'kind' => $kind->value,
            'rate' => $rate,
            'amount' => $amount,
            'iva_amount' => $this->ivaFor($purchase, $lineId, $amount),
        ];
    }

    /**
     * Base del porcentaje: el subtotal del renglón destino o, si es global,
     * el de todos los renglones (excluidos y sin cargo incluidos: el papel
     * descuenta sobre todo lo facturado).
     */
    private function percentAmount(Purchase $purchase, ?int $lineId, float $rate): float
    {
        $base = $lineId !== null
            ? (float) $purchase->lines()->whereKey($lineId)->value('subtotal')
            : (float) $purchase->lines()->sum('subtotal');

        return round($base * $rate / 100, 2);
    }

    /**
     * IVA contenido en el descuento: con la alícuota del renglón destino o,
     * si es global, con la alícuota efectiva de la factura (prorrateo).
     */
    private function ivaFor(Purchase $purchase, ?int $lineId, float $amount): float
    {
        if ($lineId !== null) {
            $rate = (float) $purchase->lines()->whereKey($lineId)->value('iva_rate');

            return round($amount * $rate, 2);
        }

        $lines = $purchase->lines()->get(['subtotal', 'iva_rate']);
        $net = (float) $lines->sum('subtotal');

        if ($net <= 0) {
            return 0.0;
        }

        $iva = (float) $lines->sum(fn ($l) => (float) $l->subtotal * (float) $l->iva_rate);

        return round($amount * $iva / $net, 2);
    }
}

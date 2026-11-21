<?php

namespace App\Models;

use App\Enums\DiscountKind;
use Database\Factories\PurchaseDiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Descuento o bonificación del comprobante, aplicado antes del IVA. Sin
 * purchase_line_id alcanza a toda la factura; con él, sólo a ese renglón.
 *
 * Afecta únicamente el total a pagar: nunca el costo imputado a los insumos.
 * amount e iva_amount se persisten siempre (los totales del índice salen por SQL).
 */
class PurchaseDiscount extends Model
{
    /** @use HasFactory<PurchaseDiscountFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'purchase_line_id',
        'description',
        'kind',
        'rate',
        'amount',
        'iva_amount',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DiscountKind::class,
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'iva_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<PurchaseLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class, 'purchase_line_id');
    }

    public function isGlobal(): bool
    {
        return $this->purchase_line_id === null;
    }
}

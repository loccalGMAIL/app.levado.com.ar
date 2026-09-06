<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use App\Enums\Unit;
use Database\Factories\PurchaseLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PurchaseLine extends Model
{
    /** @use HasFactory<PurchaseLineFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'raw_name',
        'purchaseable_type',
        'purchaseable_id',
        'quantity_purchased',
        'purchase_unit',
        'unit_price',
        'iva_rate',
        'percepcion_rate',
        'subtotal',
        'is_bonus',
        'cost_applied_at',
        'excluded_at',
        'exclusion_note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_purchased' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'iva_rate' => 'decimal:4',
            'percepcion_rate' => 'decimal:2',
            'subtotal' => 'decimal:4',
            'is_bonus' => 'boolean',
            'purchase_unit' => Unit::class,
            'cost_applied_at' => 'datetime',
            'excluded_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isIngredient(): bool
    {
        return $this->purchaseable_type === CatalogItemType::Ingredient->value;
    }

    public function isPackaging(): bool
    {
        return $this->purchaseable_type === CatalogItemType::Packaging->value;
    }

    public function isMatched(): bool
    {
        return $this->purchaseable_type !== null && $this->purchaseable_id !== null;
    }

    public function isApplied(): bool
    {
        return $this->cost_applied_at !== null;
    }

    /**
     * Renglón sin cargo: obsequio, promoción o muestra de la distribuidora.
     *
     * No es un cuarto estado sino un matiz del renglón aplicado: entra al stock
     * como cualquier compra, pero no imputa precio al catálogo (ni price log,
     * ni propagación a recetas, ni alerta de salto de costo). Por eso convive
     * con purchaseable_id y cost_applied_at, y nunca con excluded_at.
     */
    public function isBonus(): bool
    {
        return (bool) $this->is_bonus;
    }

    /**
     * Renglón que no corresponde a ningún ítem del catálogo: consumo personal
     * del titular metido en la misma factura del proveedor, un servicio
     * administrativo cobrado junto con la mercadería, u otro concepto que no
     * es un insumo. Está resuelto, pero no imputa costo ni stock.
     *
     * Los tres estados son mutuamente excluyentes: un renglón excluido nunca tiene
     * purchaseable_id ni cost_applied_at. Lo garantiza PurchaseController::matchLine(),
     * que escribe los tres campos juntos en un único update().
     */
    public function isExcluded(): bool
    {
        return $this->excluded_at !== null;
    }

    /**
     * Ya no hay nada que decidir sobre este renglón: o se imputó su costo, o se
     * marcó como "no es un insumo". Es lo que cuenta el indicador de completitud
     * de la factura (antes contaba sólo los aplicados).
     */
    public function isResolved(): bool
    {
        return $this->isApplied() || $this->isExcluded();
    }

    public function isPending(): bool
    {
        return ! $this->isResolved();
    }
}

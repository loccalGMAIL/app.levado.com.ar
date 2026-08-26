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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'purchaseable_id');
    }

    public function isIngredient(): bool
    {
        return $this->purchaseable_type === CatalogItemType::Ingredient->value;
    }

    public function isPackaging(): bool
    {
        return $this->purchaseable_type === CatalogItemType::Packaging->value;
    }

    public function isProduct(): bool
    {
        return $this->purchaseable_type === CatalogItemType::Product->value;
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
     * Renglón que no es del negocio (consumo personal del titular metido en la
     * misma factura del proveedor). Está resuelto, pero no imputa costo ni stock.
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
     * marcó como consumo personal. Es lo que cuenta el indicador de completitud
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

<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ledger inmutable de stock: cada fila es un movimiento permanente.
 * Nunca se actualiza ni se borra; toda corrección es un contramovimiento
 * nuevo que apunta al original vía reverses_movement_id.
 */
class StockMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'stockable_type',
        'stockable_id',
        'type',
        'quantity',
        'unit_cost',
        'reason',
        'reference_type',
        'reference_id',
        'reverses_movement_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Los movimientos de stock son inmutables: registrá un contramovimiento.');
        });

        static::deleting(function () {
            throw new LogicException('Los movimientos de stock no se borran: registrá un contramovimiento.');
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'stockable_id');
    }

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(Packaging::class, 'stockable_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'stockable_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    public function stockable(): Ingredient|Packaging|Product|null
    {
        return match ($this->stockable_type) {
            CatalogItemType::Ingredient->value => $this->ingredient,
            CatalogItemType::Packaging->value => $this->packaging,
            CatalogItemType::Product->value => $this->product,
            default => null,
        };
    }

    /**
     * Renglón de compra que originó el movimiento. Sólo tiene valor cuando
     * reference_type es 'purchase_line' (hoy el único tipo de referencia);
     * queda null si el renglón o su factura se borraron.
     */
    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class, 'reference_id');
    }

    public function isFromPurchaseLine(): bool
    {
        return $this->reference_type === 'purchase_line';
    }

    public function isIngredient(): bool
    {
        return $this->stockable_type === CatalogItemType::Ingredient->value;
    }

    public function isPackaging(): bool
    {
        return $this->stockable_type === CatalogItemType::Packaging->value;
    }

    public function isProduct(): bool
    {
        return $this->stockable_type === CatalogItemType::Product->value;
    }

    public function isReversal(): bool
    {
        return $this->reverses_movement_id !== null;
    }
}

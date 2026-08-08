<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isIngredient(): bool
    {
        return $this->stockable_type === CatalogItemType::Ingredient->value;
    }

    public function isPackaging(): bool
    {
        return $this->stockable_type === CatalogItemType::Packaging->value;
    }

    public function isReversal(): bool
    {
        return $this->reverses_movement_id !== null;
    }
}

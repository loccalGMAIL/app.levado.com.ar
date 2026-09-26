<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Cache derivado del ledger stock_movements: cantidad actual por ítem y sucursal.
 * Solo lo escribe StockService.
 */
class StockLevel extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'stockable_type',
        'stockable_id',
        'quantity',
        'min_quantity',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'min_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'stockable_id');
    }

    public function hasAlert(): bool
    {
        if ((float) $this->quantity < 0) {
            return true;
        }

        return $this->min_quantity !== null && (float) $this->quantity <= (float) $this->min_quantity;
    }

    /**
     * Equivalente en SQL de hasAlert(), para filtrar en la BD en vez de traer
     * todo el stock del tenant y descartar en PHP. Mantener ambas en sync.
     */
    public function scopeInAlert(Builder $query): void
    {
        $query->where(fn ($q) => $q
            ->where('quantity', '<', 0)
            ->orWhere(fn ($q2) => $q2
                ->whereNotNull('min_quantity')
                ->whereColumn('quantity', '<=', 'min_quantity')));
    }

    public function isNegative(): bool
    {
        return (float) $this->quantity < 0;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function hasAlert(): bool
    {
        if ((float) $this->quantity < 0) {
            return true;
        }

        return $this->min_quantity !== null && (float) $this->quantity <= (float) $this->min_quantity;
    }

    public function isNegative(): bool
    {
        return (float) $this->quantity < 0;
    }
}

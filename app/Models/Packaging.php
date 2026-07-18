<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PackagingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Packaging extends Model
{
    /** @use HasFactory<PackagingFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'brand',
        'supplier_id',
        'cost_per_unit',
        'cost_per_package',
        'subdivisions',
        'subdivision_label',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'cost_per_unit' => 'decimal:4',
            'cost_per_package' => 'decimal:4',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function priceLogs(): HasMany
    {
        return $this->hasMany(PackagingPriceLog::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'stockable_id')->where('stockable_type', 'packaging');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'stockable_id')->where('stockable_type', 'packaging');
    }
}

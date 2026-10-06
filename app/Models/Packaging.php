<?php

namespace App\Models;

use App\Enums\CatalogItemType;
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

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PackagingPriceLog, $this>
     */
    public function priceLogs(): HasMany
    {
        return $this->hasMany(PackagingPriceLog::class);
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'stockable_id')->where('stockable_type', CatalogItemType::Packaging->value);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'stockable_id')->where('stockable_type', CatalogItemType::Packaging->value);
    }
}

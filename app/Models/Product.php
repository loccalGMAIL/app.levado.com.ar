<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\Unit;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'recipe_id',
        'unit',
        'cost_per_unit',
        'sku',
        'barcode',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'unit' => Unit::class,
            'cost_per_unit' => 'decimal:4',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function isManufactured(): bool
    {
        return $this->type === ProductType::Manufactured;
    }

    public function isResale(): bool
    {
        return $this->type === ProductType::Resale;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function priceLogs(): HasMany
    {
        return $this->hasMany(ProductPriceLog::class);
    }
}

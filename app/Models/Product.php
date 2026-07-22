<?php

namespace App\Models;

use App\Enums\CatalogItemType;
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
        'product_category_id',
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

    /**
     * Costo directo de producción por unidad del artículo (única fuente de verdad).
     * Elaborado: cache unit_cost de la receta (insumos + mano de obra + sub-recetas,
     * mantenido por RecipeCostPropagator; sin overhead de gastos fijos).
     * Reventa: cost_per_unit (último costo, alimentado por Compras).
     * Devuelve null si todavía no hay costo determinable.
     */
    public function currentCost(): ?float
    {
        $cost = $this->isManufactured()
            ? $this->recipe?->unit_cost
            : $this->cost_per_unit;

        return $cost !== null ? (float) $cost : null;
    }

    /** Origen del costo vigente, para etiquetar en la UI. */
    public function currentCostSource(): string
    {
        return $this->isManufactured() ? 'receta' : 'compra';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function priceLogs(): HasMany
    {
        return $this->hasMany(ProductPriceLog::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'stockable_id')->where('stockable_type', CatalogItemType::Product->value);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'stockable_id')->where('stockable_type', CatalogItemType::Product->value);
    }
}

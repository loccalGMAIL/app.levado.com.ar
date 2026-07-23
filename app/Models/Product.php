<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use App\Enums\CostingMethod;
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
        'costing_method',
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
            'costing_method' => CostingMethod::class,
            'active' => 'boolean',
        ];
    }

    /**
     * Método de costeo efectivo del producto de reventa: su override o, si es null,
     * el default del negocio que se pasa.
     */
    public function effectiveCostingMethod(CostingMethod $tenantDefault): CostingMethod
    {
        return $this->costing_method ?? $tenantDefault;
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

    /**
     * Costo totalmente cargado por unidad (base del margen/pricing): costo directo
     * (currentCost) + prorrateo del overhead de gastos fijos (horas de MO de la receta
     * × overhead/hora ÷ rendimiento). La reventa no lleva overhead de producción.
     */
    public function fullCost(float $overheadPerHour): ?float
    {
        $direct = $this->currentCost();

        if ($direct === null || ! $this->isManufactured()) {
            return $direct;
        }

        $yield = (float) ($this->recipe?->yield_quantity ?? 0);
        $laborHours = (float) ($this->recipe?->labor_hours ?? 0);
        $overheadPerUnit = $yield > 0 ? $laborHours * $overheadPerHour / $yield : 0.0;

        return $direct + $overheadPerUnit;
    }

    /** Precio de venta del artículo en una lista (única fuente de verdad). Null si no tiene. */
    public function currentPrice(PriceList $priceList): ?float
    {
        $price = $this->prices()->where('price_list_id', $priceList->id)->value('price');

        return $price !== null ? (float) $price : null;
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

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
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'recipe_id',
        'recipe_quantity',
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
            'recipe_quantity' => 'decimal:3',
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

    /**
     * Elaborados que se pueden producir hoy: activos, con receta, y que NO
     * estén en una categoría marcada explícitamente "no se produce". Sin
     * categoría = producible (el default es incluir); la categoría sólo
     * sirve para EXCLUIR un área que se costea pero no se fabrica desde acá
     * (ej. cafetería). Único filtro compartido por la orden instantánea y
     * las órdenes de producción — no repetir la cadena en dos lugares.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeProducible(Builder $query): void
    {
        $query->active()
            ->where('type', ProductType::Manufactured->value)
            ->whereNotNull('recipe_id')
            ->whereNull('recipe_quantity')
            // El closure agrupa el OR: sin paréntesis el orWhereHas se
            // aplicaría contra toda la cadena de arriba y un artículo de
            // reventa en categoría producible se colaría en el resultado.
            ->where(fn (Builder $q) => $q
                ->whereNull('product_category_id')
                ->orWhereHas('category', fn (Builder $sub) => $sub->where('producible', true)));
    }

    public function isManufactured(): bool
    {
        return $this->type === ProductType::Manufactured;
    }

    /**
     * Presentación de venta: artículo con código/precio propio que toma N
     * unidades del rendimiento de una receta (ej. "Pack de 6 medialunas" de
     * la receta Facturas). No se produce: se produce el artículo base.
     */
    public function isRecipePresentation(): bool
    {
        return $this->isManufactured() && $this->recipe_quantity !== null;
    }

    /** Unidades de rendimiento de la receta que contiene 1 unidad del artículo. */
    public function recipeUnitsPerItem(): float
    {
        return $this->recipe_quantity !== null ? (float) $this->recipe_quantity : 1.0;
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
        if (! $this->isManufactured()) {
            return $this->cost_per_unit !== null ? (float) $this->cost_per_unit : null;
        }

        $recipeCost = $this->recipe?->unit_cost;

        return $recipeCost !== null ? (float) $recipeCost * $this->recipeUnitsPerItem() : null;
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

        $yield = (float) ($this->recipe->yield_quantity ?? 0);
        $laborHours = (float) ($this->recipe->labor_hours ?? 0);
        $overheadPerUnit = $yield > 0 ? $laborHours * $overheadPerHour / $yield : 0.0;

        return $direct + $overheadPerUnit * $this->recipeUnitsPerItem();
    }

    /** Precio de venta del artículo en una lista (única fuente de verdad). Null si no tiene. */
    public function currentPrice(PriceList $priceList): ?float
    {
        $price = $this->prices()->where('price_list_id', $priceList->id)->value('price');

        return $price !== null ? (float) $price : null;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Historial del precio de VENTA. Ojo: costLogs() es el del costo.
     *
     * @return HasMany<ProductPriceLog, $this>
     */
    public function priceLogs(): HasMany
    {
        return $this->hasMany(ProductPriceLog::class);
    }

    /**
     * Historial del COSTO de compra (sólo reventa). El elaborado no registra
     * logs acá — su costo vigente vive en la receta; su historial de
     * fabricaciones es productions(), no esto.
     *
     * @return HasMany<ProductCostLog, $this>
     */
    public function costLogs(): HasMany
    {
        return $this->hasMany(ProductCostLog::class);
    }

    /**
     * Última entrada del historial de costo de COMPRA, para etiquetar la
     * procedencia sin N+1. Sólo tiene sentido para reventa: en un elaborado
     * el costo vigente no sale de acá (sale de la receta), así que esto no
     * sirve para inferir procedencia de un elaborado.
     *
     * @return HasOne<ProductCostLog, $this>
     */
    public function latestCostLog(): HasOne
    {
        return $this->hasOne(ProductCostLog::class)->latestOfMany('recorded_at');
    }

    /**
     * Fabricaciones de este elaborado (vacía en la reventa). Es el historial
     * de "cuánto costó cada vez que se produjo" — no confundir con costLogs():
     * el costo VIGENTE de un elaborado sigue saliendo de la receta (currentCost()),
     * esto solo lista lo que pasó en cada evento de producción.
     *
     * @return HasMany<Production, $this>
     */
    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    /**
     * @return HasMany<StockLevel, $this>
     */
    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class, 'stockable_id')->where('stockable_type', CatalogItemType::Product->value);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'stockable_id')->where('stockable_type', CatalogItemType::Product->value);
    }
}

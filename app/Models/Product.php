<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use App\Enums\ContainerType;
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

    /**
     * Recipiente en que se pide (bandeja/lata), heredado de su receta, o null
     * si se pide en unidades. Requiere la relación `recipe` cargada.
     */
    public function containerType(): ?ContainerType
    {
        return $this->recipe?->usesContainer() ? $this->recipe->container_type : null;
    }

    /**
     * Cuántos de este artículo entran en una bandeja/lata: el rinde de la receta
     * dividido por lo que contiene el artículo (un pack x6 entra 6 veces menos).
     * Requiere la relación `recipe` cargada.
     */
    public function unitsPerContainer(): ?float
    {
        if ($this->containerType() === null) {
            return null;
        }

        return (float) $this->recipe->units_per_container / (float) ($this->recipe_quantity ?? 1);
    }

    /**
     * Datos del recipiente para la grilla de pedidos, o null si se pide en
     * unidades. Requiere la relación `recipe` cargada.
     *
     * @return array{type: string, label: string, short: string, per: float}|null
     */
    public function containerPayload(): ?array
    {
        $type = $this->containerType();

        if ($type === null) {
            return null;
        }

        return [
            'type' => $type->value,
            'label' => $type->label(),
            'short' => $type->short(),
            'per' => round((float) $this->unitsPerContainer(), 3),
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Elaborados que se pueden planificar hoy: activos, con receta, y que NO
     * estén en una categoría marcada explícitamente "no se produce". Sin
     * categoría = producible (el default es incluir); la categoría sólo
     * sirve para EXCLUIR un área que se costea pero no se fabrica desde acá
     * (ej. cafetería). Lo usan las planillas de pedidos — no repetir la
     * cadena en otro lugar.
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

    /**
     * Nombre del artículo base que ya tiene la receta, si existe (ignorando $exceptId).
     */
    public static function baseArticleNameFor(int $tenantId, int $recipeId, ?int $exceptId = null): ?string
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('recipe_id', $recipeId)
            ->where('type', ProductType::Manufactured->value)
            ->whereNull('recipe_quantity')
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->value('name');
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
     * Combo: se vende como una unidad con código y precio propios, pero su
     * stock y su costo son los de sus componentes.
     */
    public function isCombo(): bool
    {
        return $this->type === ProductType::Combo;
    }

    /**
     * Artículos con stock propio. El combo queda afuera: su existencia es la
     * de sus componentes, así que no entra en Existencias, Producción ni Compras.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeStockable(Builder $query): void
    {
        $query->where('type', '!=', ProductType::Combo->value);
    }

    /**
     * Costo directo de producción por unidad del artículo (única fuente de verdad).
     * Elaborado: cache unit_cost de la receta (insumos + descartables + mano de obra + sub-recetas,
     * mantenido por RecipeCostPropagator; sin overhead de gastos fijos).
     * Reventa: cost_per_unit (último costo, alimentado por Compras).
     * Devuelve null si todavía no hay costo determinable.
     */
    public function currentCost(): ?float
    {
        if ($this->isCombo()) {
            return $this->comboCost(fn (Product $component) => $component->currentCost());
        }

        if (! $this->isManufactured()) {
            return $this->cost_per_unit !== null ? (float) $this->cost_per_unit : null;
        }

        $recipeCost = $this->recipe?->unit_cost;

        return $recipeCost !== null ? (float) $recipeCost * $this->recipeUnitsPerItem() : null;
    }

    /** Origen del costo vigente, para etiquetar en la UI. */
    public function currentCostSource(): string
    {
        return match ($this->type) {
            ProductType::Manufactured => 'receta',
            ProductType::Combo => 'combo',
            default => 'compra',
        };
    }

    /**
     * Costo de un combo: suma del costo de cada componente por su cantidad.
     * Null si no tiene componentes o alguno todavía no tiene costo.
     *
     * @param  \Closure(Product): ?float  $componentCost
     */
    private function comboCost(\Closure $componentCost): ?float
    {
        $this->loadMissing('comboLines.component.recipe');

        if ($this->comboLines->isEmpty()) {
            return null;
        }

        $total = 0.0;

        foreach ($this->comboLines as $line) {
            $cost = $componentCost($line->component);

            if ($cost === null) {
                return null;
            }

            $total += $cost * (float) $line->quantity;
        }

        return $total;
    }

    /**
     * Costo totalmente cargado por unidad (base del margen/pricing): costo directo
     * (currentCost) + prorrateo del overhead de gastos fijos (horas de MO de la receta
     * × overhead/hora ÷ rendimiento). La reventa no lleva overhead de producción.
     */
    public function fullCost(float $overheadPerHour): ?float
    {
        if ($this->isCombo()) {
            return $this->comboCost(fn (Product $component) => $component->fullCost($overheadPerHour));
        }

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
     * Líneas de receta que usan este artículo de reventa como insumo.
     *
     * @return HasMany<RecipeProductLine, $this>
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeProductLine::class);
    }

    /**
     * Nombres de las recetas que usan este artículo como insumo.
     *
     * @return list<string>
     */
    public function recipeNamesUsingAsIngredient(bool $onlyActive = false): array
    {
        return $this->recipeLines()
            ->join('recipes', 'recipes.id', '=', 'recipe_product_lines.recipe_id')
            ->when($onlyActive, fn ($query) => $query->where('recipes.active', true))
            ->distinct()
            ->orderBy('recipes.name')
            ->pluck('recipes.name')
            ->all();
    }

    /**
     * Componentes del combo (vacía en cualquier otro tipo de artículo).
     *
     * @return HasMany<ProductComboLine, $this>
     */
    public function comboLines(): HasMany
    {
        return $this->hasMany(ProductComboLine::class, 'combo_id');
    }

    /**
     * Líneas de combo en las que este artículo participa como componente.
     *
     * @return HasMany<ProductComboLine, $this>
     */
    public function comboMemberships(): HasMany
    {
        return $this->hasMany(ProductComboLine::class, 'product_id');
    }

    /**
     * Nombres de los combos que incluyen este artículo.
     *
     * @return list<string>
     */
    public function comboNamesUsingAsComponent(bool $onlyActive = false): array
    {
        return $this->comboMemberships()
            ->join('products as combos', 'combos.id', '=', 'product_combo_lines.combo_id')
            ->when($onlyActive, fn ($query) => $query->where('combos.active', true))
            ->distinct()
            ->orderBy('combos.name')
            ->pluck('combos.name')
            ->all();
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
     * logs acá — su costo vigente vive en la receta.
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

<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\Unit;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'yield_quantity',
        'yield_unit',
        'active',
        'is_semi_elaborate',
        'unit_cost',
        'labor_hours',
    ];

    protected function casts(): array
    {
        return [
            'yield_quantity' => 'decimal:3',
            'yield_unit' => Unit::class,
            'active' => 'boolean',
            'is_semi_elaborate' => 'boolean',
            'unit_cost' => 'decimal:4',
            'labor_hours' => 'decimal:2',
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
     * @return HasMany<RecipeIngredientLine, $this>
     */
    public function ingredientLines(): HasMany
    {
        return $this->hasMany(RecipeIngredientLine::class);
    }

    /**
     * @return HasMany<RecipePackagingLine, $this>
     */
    public function packagingLines(): HasMany
    {
        return $this->hasMany(RecipePackagingLine::class);
    }

    /**
     * @return HasMany<RecipeLaborLine, $this>
     */
    public function laborLines(): HasMany
    {
        return $this->hasMany(RecipeLaborLine::class);
    }

    /**
     * @return HasMany<RecipeSubrecipeLine, $this>
     */
    public function subrecipeLines(): HasMany
    {
        return $this->hasMany(RecipeSubrecipeLine::class);
    }

    /**
     * @return HasMany<RecipeSubrecipeLine, $this>
     */
    public function parentSubrecipeLines(): HasMany
    {
        return $this->hasMany(RecipeSubrecipeLine::class, 'child_recipe_id');
    }

    /**
     * @return HasMany<RecipePrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(RecipePrice::class);
    }

    /**
     * El artículo base que produce esta receta (el precio de venta vive ahí); excluye las presentaciones.
     *
     * @return HasOne<Product, $this>
     */
    public function manufacturedProduct(): HasOne
    {
        return $this->hasOne(Product::class)
            ->where('type', ProductType::Manufactured->value)
            ->whereNull('recipe_quantity');
    }

    /**
     * Todos los artículos elaborados de la receta: el base y sus presentaciones (packs).
     *
     * @return HasMany<Product, $this>
     */
    public function manufacturedProducts(): HasMany
    {
        return $this->hasMany(Product::class)->where('type', ProductType::Manufactured->value);
    }

    /**
     * @return HasMany<RecipePriceLog, $this>
     */
    public function priceLogs(): HasMany
    {
        return $this->hasMany(RecipePriceLog::class);
    }
}

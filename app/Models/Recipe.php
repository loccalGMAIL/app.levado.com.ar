<?php

namespace App\Models;

use App\Enums\Unit;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'yield_quantity',
        'yield_unit',
        'active',
        'is_semi_elaborate',
        'unit_cost',
    ];

    protected $casts = [
        'yield_quantity' => 'decimal:3',
        'yield_unit' => Unit::class,
        'active' => 'boolean',
        'is_semi_elaborate' => 'boolean',
        'unit_cost' => 'decimal:4',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function ingredientLines(): HasMany
    {
        return $this->hasMany(RecipeIngredientLine::class);
    }

    public function packagingLines(): HasMany
    {
        return $this->hasMany(RecipePackagingLine::class);
    }

    public function laborLines(): HasMany
    {
        return $this->hasMany(RecipeLaborLine::class);
    }

    public function subrecipeLines(): HasMany
    {
        return $this->hasMany(RecipeSubrecipeLine::class);
    }

    public function parentSubrecipeLines(): HasMany
    {
        return $this->hasMany(RecipeSubrecipeLine::class, 'child_recipe_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RecipePrice::class);
    }

    public function priceLogs(): HasMany
    {
        return $this->hasMany(RecipePriceLog::class);
    }
}

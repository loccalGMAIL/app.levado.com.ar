<?php

namespace App\Models;

use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredientLine extends Model
{
    protected $fillable = [
        'recipe_id',
        'ingredient_id',
        'quantity',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit' => Unit::class,
        ];
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return BelongsTo<Ingredient, $this>
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}

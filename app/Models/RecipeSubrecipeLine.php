<?php

namespace App\Models;

use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeSubrecipeLine extends Model
{
    protected $fillable = [
        'recipe_id',
        'child_recipe_id',
        'quantity_used',
        'unit',
        'cost_calculated',
    ];

    protected function casts(): array
    {
        return [
            'quantity_used' => 'decimal:3',
            'unit' => Unit::class,
            'cost_calculated' => 'decimal:4',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function childRecipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'child_recipe_id');
    }
}

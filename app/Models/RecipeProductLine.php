<?php

namespace App\Models;

use App\Enums\Unit;
use Database\Factories\RecipeProductLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de receta que consume un artículo de reventa como insumo
 * (ej. leche chocolatada: se vende y también entra en un milkshake).
 */
class RecipeProductLine extends Model
{
    /** @use HasFactory<RecipeProductLineFactory> */
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'product_id',
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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

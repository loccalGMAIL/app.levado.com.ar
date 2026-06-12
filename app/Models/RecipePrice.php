<?php

namespace App\Models;

use Database\Factories\RecipePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipePrice extends Model
{
    /** @use HasFactory<RecipePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'price_list_id',
        'recipe_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}

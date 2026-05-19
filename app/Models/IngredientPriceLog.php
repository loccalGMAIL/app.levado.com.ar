<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientPriceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ingredient_id',
        'cost_per_unit',
        'recorded_at',
    ];

    protected $casts = [
        'cost_per_unit' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }
}

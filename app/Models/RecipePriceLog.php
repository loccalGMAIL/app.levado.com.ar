<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipePriceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'recipe_id',
        'price_list_id',
        'price',
        'recorded_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'recorded_at' => 'datetime',
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

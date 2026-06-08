<?php

namespace App\Models;

use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLine extends Model
{
    protected $fillable = [
        'purchase_id',
        'purchaseable_type',
        'purchaseable_id',
        'quantity_purchased',
        'purchase_unit',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity_purchased' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'purchase_unit' => Unit::class,
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'purchaseable_id');
    }

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(Packaging::class, 'purchaseable_id');
    }

    public function isIngredient(): bool
    {
        return $this->purchaseable_type === 'ingredient';
    }

    public function isPackaging(): bool
    {
        return $this->purchaseable_type === 'packaging';
    }
}

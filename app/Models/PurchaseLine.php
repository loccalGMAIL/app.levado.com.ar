<?php

namespace App\Models;

use App\Enums\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLine extends Model
{
    protected $fillable = [
        'purchase_id',
        'raw_name',
        'purchaseable_type',
        'purchaseable_id',
        'quantity_purchased',
        'purchase_unit',
        'unit_price',
        'iva_rate',
        'subtotal',
        'cost_applied_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_purchased' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'iva_rate' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'purchase_unit' => Unit::class,
            'cost_applied_at' => 'datetime',
        ];
    }

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

    public function isMatched(): bool
    {
        return $this->purchaseable_type !== null && $this->purchaseable_id !== null;
    }

    public function isApplied(): bool
    {
        return $this->cost_applied_at !== null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'price_list_id',
        'price',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }
}

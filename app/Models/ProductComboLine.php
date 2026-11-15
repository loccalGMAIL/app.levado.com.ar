<?php

namespace App\Models;

use Database\Factories\ProductComboLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Componente de un combo: cuántas unidades de un artículo (elaborado o de
 * reventa) lleva una unidad del combo. La cantidad va en la unidad del componente.
 */
class ProductComboLine extends Model
{
    /** @use HasFactory<ProductComboLineFactory> */
    use HasFactory;

    protected $fillable = [
        'combo_id',
        'product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function combo(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'combo_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}

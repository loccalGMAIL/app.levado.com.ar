<?php

namespace App\Models;

use App\Enums\Unit;
use Database\Factories\ProductionOrderLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de pedido: artículo + cantidad. Sin tenant_id propio — hereda por
 * el pedido, nunca se consulta suelta (ver ProductionOrderRequest).
 */
class ProductionOrderLine extends Model
{
    /** @use HasFactory<ProductionOrderLineFactory> */
    use HasFactory;

    protected $fillable = [
        'production_order_request_id',
        'product_id',
        'quantity',
        'unit',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit' => Unit::class,
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRequest::class, 'production_order_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

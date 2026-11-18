<?php

namespace App\Models;

use App\Enums\ContainerType;
use Database\Factories\CustomerOrderLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila de la semana de un cliente: un artículo y su cantidad por día
 * (day_1 = lunes … day_7 = domingo). Igual que OrderSheetLine, no lleva
 * tenant_id propio: se llega por su CustomerOrderWeek. `container_type` dice
 * en qué medida se tipeó (null = unidades; bandeja/lata = recipientes).
 */
class CustomerOrderLine extends Model
{
    /** @use HasFactory<CustomerOrderLineFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_order_week_id',
        'product_id',
        'position',
        'container_type',
        'day_1',
        'day_2',
        'day_3',
        'day_4',
        'day_5',
        'day_6',
        'day_7',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'container_type' => ContainerType::class,
            'day_1' => 'float',
            'day_2' => 'float',
            'day_3' => 'float',
            'day_4' => 'float',
            'day_5' => 'float',
            'day_6' => 'float',
            'day_7' => 'float',
        ];
    }

    /**
     * Cantidades de la semana en orden lunes → domingo.
     *
     * @return array<int, float>
     */
    public function quantities(): array
    {
        return array_map(fn (int $day): float => (float) $this->getAttribute("day_{$day}"), range(1, OrderSheetLine::DAYS));
    }

    /**
     * @return BelongsTo<CustomerOrderWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(CustomerOrderWeek::class, 'customer_order_week_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

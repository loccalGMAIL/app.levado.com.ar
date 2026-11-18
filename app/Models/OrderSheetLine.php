<?php

namespace App\Models;

use App\Enums\ContainerType;
use Database\Factories\OrderSheetLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila de la grilla semanal: un artículo y su cantidad por día
 * (day_1 = lunes … day_7 = domingo). No lleva tenant_id propio: siempre se
 * llega por su OrderSheetWeek, que sí es del negocio.
 *
 * `container_type` dice en qué medida se guardaron las cantidades: null =
 * unidades del artículo; bandeja/lata = cantidad de recipientes (lo que se
 * tipeó: si el rinde del artículo cambia, las bandejas se respetan).
 */
class OrderSheetLine extends Model
{
    /** @use HasFactory<OrderSheetLineFactory> */
    use HasFactory;

    /** Cantidad de días de la grilla (lunes a domingo). */
    public const DAYS = 7;

    protected $fillable = [
        'order_sheet_week_id',
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
        return array_map(fn (int $day): float => (float) $this->getAttribute("day_{$day}"), range(1, self::DAYS));
    }

    public function weekTotal(): float
    {
        return array_sum($this->quantities());
    }

    /**
     * @return BelongsTo<OrderSheetWeek, $this>
     */
    public function week(): BelongsTo
    {
        return $this->belongsTo(OrderSheetWeek::class, 'order_sheet_week_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

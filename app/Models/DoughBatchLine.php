<?php

namespace App\Models;

use App\Enums\WasteReason;
use Database\Factories\DoughBatchLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón de un amasado: un artículo base con lo planeado, lo real y la
 * merma, en unidades del artículo. La merma del amasado no mueve stock de
 * producto (esas piezas nunca entraron): queda acá, valorizada a `unit_cost`.
 * Sin tenant_id propio: se llega por su DoughBatch.
 */
class DoughBatchLine extends Model
{
    /** @use HasFactory<DoughBatchLineFactory> */
    use HasFactory;

    protected $fillable = [
        'dough_batch_id',
        'product_id',
        'position',
        'goes_to_chamber',
        'planned_units',
        'actual_units',
        'waste_units',
        'waste_reason',
        'waste_note',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'goes_to_chamber' => 'boolean',
            'planned_units' => 'float',
            'actual_units' => 'float',
            'waste_units' => 'float',
            'waste_reason' => WasteReason::class,
            'unit_cost' => 'float',
        ];
    }

    /** Merma valorizada al costo del renglón (snapshot al confirmar). */
    public function wasteCost(): float
    {
        return $this->waste_units * (float) $this->unit_cost;
    }

    /** Lo que salió de la masa: lo real más la merma. */
    public function producedUnits(): float
    {
        return $this->actual_units + $this->waste_units;
    }

    /**
     * @return BelongsTo<DoughBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DoughBatch::class, 'dough_batch_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

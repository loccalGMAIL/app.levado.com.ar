<?php

namespace App\Models;

use App\Enums\WasteReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renglón de un cierre de cámara, en unidades del artículo base. Lo esperado
 * es el stock en cámara al abrir el cierre: los movimientos se calculan
 * contra eso, así que lo que entre a cámara mientras se cuenta no se toma
 * como horneado.
 */
class ChamberClosingLine extends Model
{
    protected $fillable = [
        'chamber_closing_id',
        'product_id',
        'expected_units',
        'counted_units',
        'waste_units',
        'waste_reason',
        'waste_note',
        'baked_units',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'expected_units' => 'float',
            'counted_units' => 'float',
            'waste_units' => 'float',
            'waste_reason' => WasteReason::class,
            'baked_units' => 'float',
            'unit_cost' => 'float',
        ];
    }

    /** Horneado = esperado − contado − merma. Negativo si se contó de más. */
    public function computedBakedUnits(): float
    {
        return round($this->expected_units - $this->counted_units - $this->waste_units, 3);
    }

    /**
     * @return BelongsTo<ChamberClosing, $this>
     */
    public function closing(): BelongsTo
    {
        return $this->belongsTo(ChamberClosing::class, 'chamber_closing_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

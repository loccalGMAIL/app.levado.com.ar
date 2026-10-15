<?php

namespace App\Models;

use App\Enums\Jurisdiction;
use App\Enums\PerceptionType;
use Database\Factories\PurchasePerceptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Percepción o impuesto del comprobante. type/jurisdiction/description son
 * snapshot del concepto: editar o borrar el catálogo no reescribe facturas viejas.
 *
 * base_amount null = automática (neto gravado de la compra). amount es siempre
 * el importe persistido; si amount_is_manual manda lo escrito, si no se recalcula.
 */
class PurchasePerception extends Model
{
    /** @use HasFactory<PurchasePerceptionFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'perception_concept_id',
        'type',
        'jurisdiction',
        'description',
        'base_amount',
        'rate',
        'amount',
        'amount_is_manual',
    ];

    protected function casts(): array
    {
        return [
            'type' => PerceptionType::class,
            'jurisdiction' => Jurisdiction::class,
            'base_amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'amount_is_manual' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<PerceptionConcept, $this>
     */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(PerceptionConcept::class, 'perception_concept_id');
    }

    public function usesAutomaticBase(): bool
    {
        return $this->base_amount === null;
    }
}

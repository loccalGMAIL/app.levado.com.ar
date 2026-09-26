<?php

namespace App\Models;

use App\Enums\Unit;
use Database\Factories\RecurringProductionRequestLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Artículo + cantidad plantilla de un pedido recurrente — espejo exacto de
 * ProductionOrderLine. Sin tenant_id propio, igual que su espejo: tenant-safe
 * por el padre (RecurringProductionRequest).
 */
class RecurringProductionRequestLine extends Model
{
    /** @use HasFactory<RecurringProductionRequestLineFactory> */
    use HasFactory;

    protected $fillable = [
        'recurring_production_request_id',
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

    public function recurringProductionRequest(): BelongsTo
    {
        return $this->belongsTo(RecurringProductionRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

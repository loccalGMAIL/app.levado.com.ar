<?php

namespace App\Models;

use App\Enums\Unit;
use Database\Factories\CreditNoteLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteLine extends Model
{
    /** @use HasFactory<CreditNoteLineFactory> */
    use HasFactory;

    protected $fillable = [
        'credit_note_id',
        'purchase_line_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'iva_rate',
        'subtotal',
        'affects_stock',
        'stock_applied_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit' => Unit::class,
            'unit_price' => 'decimal:4',
            'iva_rate' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'affects_stock' => 'boolean',
            'stock_applied_at' => 'datetime',
        ];
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }

    public function isLinkedToPurchaseLine(): bool
    {
        return $this->purchase_line_id !== null;
    }

    /**
     * Si el renglón debe descontar stock. Sólo tiene efecto real cuando está
     * atado a un renglón de compra: sin origen no hay entrada que revertir.
     */
    public function affectsStock(): bool
    {
        return (bool) $this->affects_stock && $this->isLinkedToPurchaseLine();
    }

    public function isStockApplied(): bool
    {
        return $this->stock_applied_at !== null;
    }

    public function isPending(): bool
    {
        return $this->affectsStock() && ! $this->isStockApplied();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Atributo agregado por un join en NotificationService.
 *
 * @property-read int|string|null $pending_lines_count
 */
class Purchase extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'invoice_number',
        'invoice_date',
        'invoice_total',
        'default_iva_rate',
        'notes',
        'invoice_image_path',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'invoice_total' => 'decimal:2',
            'default_iva_rate' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PurchaseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * @return HasMany<PurchasePerception, $this>
     */
    public function perceptions(): HasMany
    {
        return $this->hasMany(PurchasePerception::class);
    }

    /**
     * Base automática de las percepciones: el neto de los renglones gravados con
     * IVA. Una compra sin ningún renglón gravado (sin factura, exenta) cae al neto total.
     */
    public function taxedNet(): float
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get(['subtotal', 'iva_rate']);

        $taxed = $lines->filter(fn ($l) => (float) $l->iva_rate > 0)->sum(fn ($l) => (float) $l->subtotal);

        return (float) ($taxed > 0 ? $taxed : $lines->sum(fn ($l) => (float) $l->subtotal));
    }

    public function perceptionsTotal(): float
    {
        return (float) ($this->relationLoaded('perceptions')
            ? $this->perceptions->sum('amount')
            : $this->perceptions()->sum('amount'));
    }

    public function totalAmount(): float
    {
        return (float) ($this->relationLoaded('lines')
            ? $this->lines->sum('subtotal')
            : $this->lines()->sum('subtotal'));
    }
}

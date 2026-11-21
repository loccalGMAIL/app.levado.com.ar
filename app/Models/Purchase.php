<?php

namespace App\Models;

use App\Enums\PerceptionBase;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
     * @return HasMany<PurchaseDiscount, $this>
     */
    public function discounts(): HasMany
    {
        return $this->hasMany(PurchaseDiscount::class);
    }

    /**
     * Renglones con su neto ya descontado. Un descuento de renglón resta de ese
     * renglón; uno global se prorratea entre todos según su subtotal. Es la base
     * de las percepciones automáticas: el descuento va antes del IVA.
     *
     * @return Collection<int, array{net: float, iva_rate: float}>
     */
    private function discountedLines(): Collection
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get(['id', 'subtotal', 'iva_rate']);
        $discounts = $this->relationLoaded('discounts') ? $this->discounts : $this->discounts()->get(['purchase_line_id', 'amount']);

        $byLine = $discounts->whereNotNull('purchase_line_id')
            ->groupBy('purchase_line_id')
            ->map(fn ($group) => (float) $group->sum('amount'));
        $global = (float) $discounts->whereNull('purchase_line_id')->sum('amount');
        $total = (float) $lines->sum(fn ($l) => (float) $l->subtotal);

        return $lines->map(function ($line) use ($byLine, $global, $total) {
            $subtotal = (float) $line->subtotal;
            $allocated = ($byLine[$line->id] ?? 0.0) + ($total > 0 ? $global * $subtotal / $total : 0.0);

            return ['net' => $subtotal - $allocated, 'iva_rate' => (float) $line->iva_rate];
        });
    }

    /**
     * Base automática de las percepciones: el neto (ya descontado) de los renglones
     * gravados con IVA. Una compra sin ningún renglón gravado (sin factura, exenta)
     * cae al neto total.
     */
    public function taxedNet(): float
    {
        $lines = $this->discountedLines();

        $taxed = $lines->filter(fn ($l) => $l['iva_rate'] > 0)->sum('net');

        return (float) ($taxed > 0 ? $taxed : $lines->sum('net'));
    }

    /**
     * Importe sobre el que calcula una percepción automática, según la base que
     * defina su concepto: neto gravado, neto total de los renglones o total con IVA.
     */
    public function baseFor(PerceptionBase $base): float
    {
        if ($base === PerceptionBase::NetTaxed) {
            return $this->taxedNet();
        }

        $lines = $this->discountedLines();

        return (float) match ($base) {
            PerceptionBase::NetTotal => $lines->sum('net'),
            PerceptionBase::Gross => $lines->sum(fn ($l) => $l['net'] * (1 + $l['iva_rate'])),
        };
    }

    public function discountsTotal(): float
    {
        return (float) ($this->relationLoaded('discounts')
            ? $this->discounts->sum('amount')
            : $this->discounts()->sum('amount'));
    }

    public function discountsIva(): float
    {
        return (float) ($this->relationLoaded('discounts')
            ? $this->discounts->sum('iva_amount')
            : $this->discounts()->sum('iva_amount'));
    }

    /**
     * Alícuota de IVA de la factura tal como la ven los radios del formulario ('0', '0.105'
     * o '0.21'). Si todos los renglones comparten alícuota, esa es la de la factura: es lo que
     * realmente se está pagando, aunque el default de cabecera diga otra cosa o sea nulo (compras
     * viejas). Con renglones mezclados, o sin renglones, vale el default de la cabecera.
     */
    public function ivaRateForForm(): string
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : $this->lines()->get(['iva_rate']);

        $rates = $lines->map(fn ($l) => round((float) $l->iva_rate, 4))->unique()->values();

        $rate = $rates->count() === 1
            ? $rates->first()
            : ($this->default_iva_rate !== null ? (float) $this->default_iva_rate : 0.21);

        return rtrim(rtrim(number_format($rate, 4, '.', ''), '0'), '.') ?: '0';
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

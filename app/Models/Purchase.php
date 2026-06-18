<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'invoice_number',
        'invoice_date',
        'invoice_total',
        'default_iva_rate',
        'default_percepcion_rate',
        'notes',
        'invoice_image_path',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'invoice_total' => 'decimal:2',
            'default_iva_rate' => 'decimal:4',
            'default_percepcion_rate' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    public function totalAmount(): float
    {
        return (float) $this->lines()->sum('subtotal');
    }
}

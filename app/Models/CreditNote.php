<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CreditNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditNote extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CreditNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'purchase_id',
        'note_number',
        'note_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'date',
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

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class);
    }

    public function totalAmount(): float
    {
        return (float) ($this->relationLoaded('lines')
            ? $this->lines->sum('subtotal')
            : $this->lines()->sum('subtotal'));
    }
}

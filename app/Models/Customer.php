<?php

namespace App\Models;

use App\Enums\CondicionIva;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'address',
        'city',
        'notes',
        'active',
        'delivery_person_id',
        'legal_name',
        'tax_id',
        'condicion_iva',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'condicion_iva' => CondicionIva::class,
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Repartidor a cargo de este cliente, si tiene uno asignado. */
    public function deliveryPerson(): BelongsTo
    {
        return $this->belongsTo(DeliveryPerson::class);
    }
}

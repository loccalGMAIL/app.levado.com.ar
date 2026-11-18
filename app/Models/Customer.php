<?php

namespace App\Models;

use App\Enums\CondicionIva;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'route_position',
        'legal_name',
        'tax_id',
        'condicion_iva',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'route_position' => 'integer',
            'condicion_iva' => CondicionIva::class,
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Repartidor a cargo de este cliente, si tiene uno asignado.
     *
     * @return BelongsTo<DeliveryPerson, $this>
     */
    public function deliveryPerson(): BelongsTo
    {
        return $this->belongsTo(DeliveryPerson::class);
    }

    /**
     * Semanas de pedidos de este cliente.
     *
     * @return HasMany<CustomerOrderWeek, $this>
     */
    public function orderWeeks(): HasMany
    {
        return $this->hasMany(CustomerOrderWeek::class);
    }
}

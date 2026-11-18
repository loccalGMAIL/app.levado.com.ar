<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerOrderWeekFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una semana (lunes a domingo) de pedidos de un cliente. `starts_on` es
 * siempre el lunes.
 */
class CustomerOrderWeek extends Model
{
    /** @use HasFactory<CustomerOrderWeekFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'starts_on',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<CustomerOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerOrderLine::class)->orderBy('position')->orderBy('id');
    }
}

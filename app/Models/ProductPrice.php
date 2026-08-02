<?php

namespace App\Models;

use App\Enums\PricingPolicy;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'price_list_id',
        'product_id',
        'price',
        'policy_type',
        'policy_value',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'policy_type' => PricingPolicy::class,
            'policy_value' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * Forma que consumen las celdas de precio inline (factory JS priceCell):
     * la política de precio y su valor. type siempre presente (default manual).
     *
     * @return array{type: string, value: float|null}
     */
    public function policyPayload(): array
    {
        return [
            'type' => $this->policy_type?->value ?? PricingPolicy::Manual->value,
            'value' => $this->policy_value !== null ? (float) $this->policy_value : null,
        ];
    }
}

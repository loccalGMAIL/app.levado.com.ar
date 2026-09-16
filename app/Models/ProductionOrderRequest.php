<?php

namespace App\Models;

use App\Enums\DeliveryDestinationType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductionOrderRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un pedido dentro de una orden de producción: lo que hay que llevar a un
 * destino (sucursal o repartidor). Tiene tenant_id propio porque se lee desde
 * la UI por fuera de su orden ("qué lleva el repartidor Juan").
 */
class ProductionOrderRequest extends Model
{
    /** @use HasFactory<ProductionOrderRequestFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'destination_type',
        'destination_id',
        'notes',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'destination_type' => DeliveryDestinationType::class,
        ];
    }

    /** "Pedido 1", "Pedido 2"... — position reusada como número dentro de la orden. */
    public function numberLabel(): string
    {
        return "Pedido {$this->position}";
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function destination(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionOrderLine::class);
    }
}

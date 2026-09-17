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
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un pedido dentro de una orden de producción: lo que hay que llevar a un
 * destino (sucursal o repartidor). Tiene tenant_id propio porque se lee desde
 * la UI por fuera de su orden ("qué lleva el repartidor Juan").
 *
 * SoftDeletes a propósito (no sólo por la convención de baja lógica): el
 * materializador de recurrencia necesita distinguir "nunca se generó" de
 * "se generó y lo borraron" — con DELETE físico no podría, y regeneraría lo
 * que alguien borró a mano en la próxima corrida.
 */
class ProductionOrderRequest extends Model
{
    /** @use HasFactory<ProductionOrderRequestFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'destination_type',
        'destination_id',
        'notes',
        'position',
        'number',
    ];

    protected function casts(): array
    {
        return [
            'destination_type' => DeliveryDestinationType::class,
        ];
    }

    /**
     * "Pedido #123" — number es correlativo por negocio (identidad propia
     * del pedido, independiente de su orden). Fallback a position ("Pedido 1")
     * sólo para filas viejas de plantillas, que nunca consumieron número.
     */
    public function numberLabel(): string
    {
        return $this->number !== null ? "Pedido #{$this->number}" : "Pedido {$this->position}";
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

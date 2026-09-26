<?php

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionOrderType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\ExcludeTemplatesScope;
use Database\Factories\ProductionOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orden de producción: agrupa pedidos (uno por destino) que se producen
 * juntos. Diaria (los pedidos normales del día) o espontánea (lo que hay
 * que fabricar en el momento). Las plantillas viven en esta misma tabla
 * (is_template = true, scheduled_for NULL) — ver ExcludeTemplatesScope.
 */
class ProductionOrder extends Model
{
    /** @use HasFactory<ProductionOrderFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'type',
        'number',
        'scheduled_for',
        'status',
        'is_template',
        'name',
        'notes',
        'user_id',
        'confirmed_at',
        'produced_at',
        'cancelled_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ExcludeTemplatesScope);
    }

    protected function casts(): array
    {
        return [
            'type' => ProductionOrderType::class,
            'scheduled_for' => 'date',
            'status' => ProductionOrderStatus::class,
            'is_template' => 'boolean',
            'confirmed_at' => 'datetime',
            'produced_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** Incluye plantillas además de las órdenes normales. */
    public function scopeWithTemplates(Builder $query): void
    {
        $query->withoutGlobalScope(ExcludeTemplatesScope::class);
    }

    /** Sólo plantillas — para la pantalla dedicada. */
    public function scopeOnlyTemplates(Builder $query): void
    {
        $query->withoutGlobalScope(ExcludeTemplatesScope::class)->where('is_template', true);
    }

    public function isDraft(): bool
    {
        return $this->status === ProductionOrderStatus::Draft;
    }

    public function isCancelled(): bool
    {
        return $this->status === ProductionOrderStatus::Cancelled;
    }

    public function isDone(): bool
    {
        return $this->status === ProductionOrderStatus::Done;
    }

    /** Se pueden seguir editando sus pedidos y líneas: no terminada ni anulada. */
    public function isEditable(): bool
    {
        return ! $this->isDone() && ! $this->isCancelled();
    }

    /** "Orden #7", o el nombre si es una plantilla (no lleva número). */
    public function numberLabel(): string
    {
        return $this->number !== null ? "Orden #{$this->number}" : ($this->name ?? 'Plantilla sin nombre');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Nombrada en plural completo (no `requests()`) a propósito: el scoped
     * binding de la ruta anidada production-orders/{productionOrder}/requests/
     * {productionOrderRequest} busca esta relación por convención (plural del
     * parámetro de ruta) — ver RecipeLineController para el mismo patrón.
     */
    public function productionOrderRequests(): HasMany
    {
        return $this->hasMany(ProductionOrderRequest::class);
    }

    /** Las Production que nació producir esta orden (una por artículo agregado). */
    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }
}

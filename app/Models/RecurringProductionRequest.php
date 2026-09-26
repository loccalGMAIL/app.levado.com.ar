<?php

namespace App\Models;

use App\Enums\DeliveryDestinationType;
use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Database\Factories\RecurringProductionRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * El molde de un pedido recurrente ("todos los lunes a sábado a la
 * Cafetería"): destino + días de semana en que se repite. Las instancias
 * reales son ProductionOrderRequest normales — editables una por una, sin
 * afectar al resto — vinculadas acá vía
 * ProductionOrderRequest::recurring_production_request_id. Generarlas es
 * responsabilidad de RecurringProductionRequestMaterializer, no de este modelo.
 */
class RecurringProductionRequest extends Model
{
    /** @use HasFactory<RecurringProductionRequestFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'destination_type',
        'destination_id',
        'weekdays',
        'starts_on',
        'ends_on',
        'active',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'destination_type' => DeliveryDestinationType::class,
            'weekdays' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Si el recurrente genera una instancia para esa fecha: el día de
     * semana ISO-8601 (1=lunes...7=domingo — dayOfWeekIso, NUNCA dayOfWeek
     * que es 0=domingo) está en weekdays, y la fecha cae dentro de la
     * vigencia (starts_on/ends_on).
     */
    public function occursOn(CarbonInterface $date): bool
    {
        if (! in_array($date->dayOfWeekIso, $this->weekdays, true)) {
            return false;
        }

        if ($date->lt($this->starts_on)) {
            return false;
        }

        return $this->ends_on === null || $date->lte($this->ends_on);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destination(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecurringProductionRequestLine::class);
    }

    /** Las instancias reales que este recurrente ya generó. */
    public function instances(): HasMany
    {
        return $this->hasMany(ProductionOrderRequest::class);
    }
}

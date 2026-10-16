<?php

namespace App\Models;

use App\Enums\Jurisdiction;
use App\Enums\PerceptionBase;
use App\Enums\PerceptionType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PerceptionConceptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de percepciones/impuestos del negocio («Perc. IIBB Buenos Aires 3%»).
 * La compra copia estos datos como snapshot al usarlo (PurchasePerception).
 */
class PerceptionConcept extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PerceptionConceptFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'type',
        'jurisdiction',
        'name',
        'default_rate',
        'base_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PerceptionType::class,
            'jurisdiction' => Jurisdiction::class,
            'default_rate' => 'decimal:4',
            'base_type' => PerceptionBase::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Forma que consumen los modales de percepciones (JSON y estado Alpine).
     *
     * @return array{id: int, name: string, type: string, type_label: string, jurisdiction: ?string, jurisdiction_label: ?string, default_rate: ?float, base_type: string, is_active: bool}
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'jurisdiction' => $this->jurisdiction?->value,
            'jurisdiction_label' => $this->jurisdiction?->label(),
            'default_rate' => $this->default_rate !== null ? (float) $this->default_rate : null,
            'base_type' => $this->base_type->value,
            'is_active' => $this->is_active,
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<PurchasePerception, $this>
     */
    public function purchasePerceptions(): HasMany
    {
        return $this->hasMany(PurchasePerception::class);
    }
}

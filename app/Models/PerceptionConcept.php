<?php

namespace App\Models;

use App\Enums\Jurisdiction;
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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => PerceptionType::class,
            'jurisdiction' => Jurisdiction::class,
            'default_rate' => 'decimal:4',
            'is_active' => 'boolean',
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

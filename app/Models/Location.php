<?php

namespace App\Models;

use App\Enums\LocationKind;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToTenant, HasFactory;

    /** @var array<string, mixed> Espejo del default de la columna, para modelos recién creados. */
    protected $attributes = [
        'kind' => 'store',
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'city',
        'kind',
        'is_default',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => LocationKind::class,
            'is_default' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * Ubicaciones de tipo sucursal (las que pueden ser default y recibir compras).
     *
     * @param  Builder<Location>  $query
     */
    public function scopeStores(Builder $query): void
    {
        $query->where('kind', LocationKind::Store->value);
    }

    public function isProofingChamber(): bool
    {
        return $this->kind === LocationKind::ProofingChamber;
    }

    /** Si ya tiene movimientos de stock (entonces no puede cambiar de tipo). */
    public function hasStockMovements(): bool
    {
        return StockMovement::query()->where('location_id', $this->id)->exists();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

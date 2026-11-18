<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\OrderSheetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Planilla de pedidos de un negocio (ej. Secos, Diarios, Tortas, Reparto):
 * una grilla lunes–domingo por artículo con lo que se va a elaborar cada día.
 * El contenido vive en sus semanas (OrderSheetWeek).
 */
class OrderSheet extends Model
{
    /** @use HasFactory<OrderSheetFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'position',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @param  Builder<OrderSheet>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * @param  Builder<OrderSheet>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<OrderSheetWeek, $this>
     */
    public function weeks(): HasMany
    {
        return $this->hasMany(OrderSheetWeek::class);
    }
}

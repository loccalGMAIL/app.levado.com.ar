<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoría de artículos por negocio (Panadería, Cafetería, Pastelería…).
 * El flag `producible` decide si los elaborados de la categoría aparecen en el
 * select de Producción: los de una categoría no-producible (o sin categoría) se
 * ocultan, aunque tengan receta.
 */
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'producible',
    ];

    protected function casts(): array
    {
        return [
            'producible' => 'boolean',
        ];
    }

    public function scopeProducible(Builder $query): void
    {
        $query->where('producible', true);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

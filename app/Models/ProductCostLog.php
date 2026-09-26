<?php

namespace App\Models;

use App\Enums\CostLogSource;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductCostLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial del costo de un artículo de REVENTA: cada vez que cambia
 * products.cost_per_unit queda una fila con su procedencia.
 *
 * El elaborado no participa: su costo vive en la receta y se reconstruye desde
 * los logs de precio de los insumos.
 *
 * Filas inmutables: recorded_at es la marca semántica y no hay updated_at
 * (mismo criterio que ingredient_price_logs / packaging_price_logs). A
 * diferencia de esas dos, esta tabla SÍ se lee desde la UI, y por eso lleva
 * tenant_id y el vínculo a la línea de compra que la originó.
 */
class ProductCostLog extends Model
{
    /** @use HasFactory<ProductCostLogFactory> */
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'purchase_line_id',
        'cost_per_unit',
        'source',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_per_unit' => 'decimal:4',
            'source' => CostLogSource::class,
            'recorded_at' => 'datetime',
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

    public function purchaseLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseLine::class);
    }
}

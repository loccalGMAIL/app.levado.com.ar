<?php

namespace App\Models;

use App\Enums\CatalogItemType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SupplierProductLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un texto de renglón de factura, para un proveedor dado, resuelto a un ítem
 * del catálogo. Lo escribe ProductLinkMemory a partir de decisiones humanas.
 */
class SupplierProductLink extends Model
{
    /** @use HasFactory<SupplierProductLinkFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'raw_name_normalized',
        'raw_name_sample',
        'purchaseable_type',
        'purchaseable_id',
        'pkg_qty',
        'times_confirmed',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'pkg_qty' => 'decimal:4',
            'times_confirmed' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isIngredient(): bool
    {
        return $this->purchaseable_type === CatalogItemType::Ingredient->value;
    }
}

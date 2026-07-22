<?php

namespace App\Models;

use App\Enums\ProductionStatus;
use App\Enums\Unit;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro de una producción: la fabricación de un producto elaborado, que
 * descuenta los insumos base de su receta (BOM explotado) y suma stock del
 * producto. Los movimientos de stock viven en el ledger inmutable, atados por
 * (reference_type='production', reference_id=id); acá va el cabezal/snapshot.
 */
class Production extends Model
{
    /** @use HasFactory<ProductionFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'location_id',
        'product_id',
        'recipe_id',
        'quantity',
        'unit',
        'unit_cost',
        'total_cost',
        'status',
        'notes',
        'user_id',
        'produced_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit' => Unit::class,
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'status' => ProductionStatus::class,
            'produced_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->status === ProductionStatus::Confirmed;
    }

    public function isCancelled(): bool
    {
        return $this->status === ProductionStatus::Cancelled;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Movimientos de stock generados por esta producción (consumos de insumos +
     * entrada del elaborado), atados por la referencia genérica del ledger.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')
            ->where('reference_type', 'production');
    }
}

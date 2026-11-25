<?php

namespace App\Models;

use App\Enums\ProductionRecordStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DoughBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Amasado de un día: lo que realmente se amasó, con renglones mixtos (a
 * cámara y directo). Lo escribe DoughBatchService; los movimientos de stock
 * que genera quedan atados por `reference_type = 'dough_batch'`.
 */
class DoughBatch extends Model
{
    /** @use HasFactory<DoughBatchFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** Discriminador de sus movimientos en stock_movements.reference_type. */
    public const REFERENCE_TYPE = 'dough_batch';

    protected $fillable = [
        'tenant_id',
        'number',
        'kitchen_location_id',
        'dough_date',
        'status',
        'notes',
        'confirmed_at',
        'confirmed_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'dough_date' => 'date',
            'status' => ProductionRecordStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === ProductionRecordStatus::Draft;
    }

    public function isConfirmed(): bool
    {
        return $this->status === ProductionRecordStatus::Confirmed;
    }

    /** Piezas del amasado: lo real si está confirmado, lo planeado si es borrador. Requiere `lines`. */
    public function totalUnits(): float
    {
        return (float) $this->lines->sum(fn (DoughBatchLine $line) => $this->isDraft() ? $line->planned_units : $line->actual_units);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function kitchenLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'kitchen_location_id');
    }

    /**
     * @return HasMany<DoughBatchLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DoughBatchLine::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<DoughBatchMass, $this>
     */
    public function masses(): HasMany
    {
        return $this->hasMany(DoughBatchMass::class)->orderBy('id');
    }

    /**
     * Movimientos de stock que generó (consumos, entradas y sus reversas).
     *
     * @return HasMany<StockMovement, $this>
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')->where('reference_type', self::REFERENCE_TYPE);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}

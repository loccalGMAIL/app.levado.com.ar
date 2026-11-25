<?php

namespace App\Models;

use App\Enums\ProductionRecordStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ChamberClosingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cierre de cámara (horneado): lo que se contó en la cámara y lo que, por
 * diferencia, pasó a terminado. Lo escribe ChamberClosingService; sus
 * movimientos quedan atados por `reference_type = 'chamber_closing'`.
 */
class ChamberClosing extends Model
{
    /** @use HasFactory<ChamberClosingFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** Discriminador de sus movimientos en stock_movements.reference_type. */
    public const REFERENCE_TYPE = 'chamber_closing';

    protected $fillable = [
        'tenant_id',
        'number',
        'chamber_location_id',
        'status',
        'opened_at',
        'confirmed_at',
        'confirmed_by',
        'cancelled_at',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => ProductionRecordStatus::class,
            'opened_at' => 'datetime',
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

    /**
     * @return BelongsTo<Location, $this>
     */
    public function chamberLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'chamber_location_id');
    }

    /**
     * @return HasMany<ChamberClosingLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ChamberClosingLine::class)->orderBy('id');
    }

    /**
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

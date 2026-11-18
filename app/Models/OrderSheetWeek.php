<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\OrderSheetWeekFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una semana (lunes a domingo) de una planilla de pedidos. `starts_on` es
 * siempre el lunes.
 */
class OrderSheetWeek extends Model
{
    /** @use HasFactory<OrderSheetWeekFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'order_sheet_id',
        'starts_on',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<OrderSheet, $this>
     */
    public function orderSheet(): BelongsTo
    {
        return $this->belongsTo(OrderSheet::class);
    }

    /**
     * @return HasMany<OrderSheetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderSheetLine::class)->orderBy('position')->orderBy('id');
    }
}

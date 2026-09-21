<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FixedCostLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['fixed_cost_id', 'monthly_amount', 'period'];

    /**
     * `fromDateTime()` -lo que Eloquent usa para serializar 'period' al
     * guardar- ignora el formato del cast (`date:Y-m-d`) y siempre usa
     * `getDateFormat()`, que por defecto trae la hora. MySQL la trunca solo
     * porque la columna es DATE; SQLite (dynamic typing, usado en los tests)
     * la guarda tal cual, y entonces una comparación por string contra
     * 'Y-m-d' (como hace FixedCostHistory) nunca matchea.
     */
    protected $dateFormat = 'Y-m-d';

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'period' => 'date',
        ];
    }

    public function fixedCost(): BelongsTo
    {
        return $this->belongsTo(FixedCost::class);
    }

    public function scopeUpToPeriod(Builder $query, Carbon $period): void
    {
        $query->where('period', '<=', $period->copy()->startOfMonth());
    }
}

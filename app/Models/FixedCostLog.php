<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedCostLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['fixed_cost_id', 'monthly_amount', 'valid_from'];

    protected $casts = [
        'monthly_amount' => 'decimal:2',
        'valid_from' => 'date',
    ];

    public function fixedCost(): BelongsTo
    {
        return $this->belongsTo(FixedCost::class);
    }
}

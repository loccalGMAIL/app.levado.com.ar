<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagingPriceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'packaging_id',
        'cost_per_unit',
        'recorded_at',
    ];

    protected $casts = [
        'cost_per_unit' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(Packaging::class);
    }
}

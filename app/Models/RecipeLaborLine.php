<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeLaborLine extends Model
{
    protected $fillable = [
        'recipe_id',
        'labor_type_id',
        'hours',
    ];

    protected $casts = [
        'hours' => 'decimal:2',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function laborType(): BelongsTo
    {
        return $this->belongsTo(LaborType::class);
    }
}

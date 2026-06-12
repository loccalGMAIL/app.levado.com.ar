<?php

namespace App\Models;

use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'adjustment_pct',
        'is_default',
        'active',
    ];

    protected $casts = [
        'adjustment_pct' => 'decimal:2',
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(RecipePrice::class);
    }
}

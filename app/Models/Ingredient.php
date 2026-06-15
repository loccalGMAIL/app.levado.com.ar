<?php

namespace App\Models;

use App\Enums\Unit;
use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'brand',
        'supplier_id',
        'unit',
        'cost_per_unit',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'unit' => Unit::class,
            'cost_per_unit' => 'decimal:4',
            'active' => 'boolean',
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

    public function priceLogs(): HasMany
    {
        return $this->hasMany(IngredientPriceLog::class);
    }
}

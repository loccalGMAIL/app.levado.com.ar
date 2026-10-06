<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FixedCostCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedCostCategory extends Model
{
    /** @use HasFactory<FixedCostCategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name'];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<FixedCost, $this>
     */
    public function fixedCosts(): HasMany
    {
        return $this->hasMany(FixedCost::class);
    }
}

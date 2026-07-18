<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FixedCostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedCost extends Model
{
    /** @use HasFactory<FixedCostFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'fixed_cost_category_id', 'name', 'monthly_amount', 'active'];

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedCostCategory::class, 'fixed_cost_category_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(FixedCostLog::class);
    }
}

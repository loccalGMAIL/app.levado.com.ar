<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\LaborTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborType extends Model
{
    /** @use HasFactory<LaborTypeFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'hourly_rate', 'active'];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
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
}

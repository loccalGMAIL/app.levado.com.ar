<?php

namespace App\Models;

use Database\Factories\LaborTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborType extends Model
{
    /** @use HasFactory<LaborTypeFactory> */
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'hourly_rate', 'active'];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

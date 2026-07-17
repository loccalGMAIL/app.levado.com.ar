<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VariableExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VariableExpenseCategory extends Model
{
    /** @use HasFactory<VariableExpenseCategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function variableExpenses(): HasMany
    {
        return $this->hasMany(VariableExpense::class);
    }
}

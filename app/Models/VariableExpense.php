<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VariableExpenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gasto ocasional o imprevisto. A diferencia de FixedCost, no interviene en el
 * cálculo de costos de recetas: existe sólo como registro administrativo.
 */
class VariableExpense extends Model
{
    /** @use HasFactory<VariableExpenseFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'variable_expense_category_id', 'supplier_id', 'name', 'description', 'amount', 'expense_date', 'receipt_image_path'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query->when($from, fn ($q, $date) => $q->where('expense_date', '>=', $date))
            ->when($to, fn ($q, $date) => $q->where('expense_date', '<=', $date));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VariableExpenseCategory::class, 'variable_expense_category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}

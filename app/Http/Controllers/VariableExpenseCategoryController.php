<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\VariableExpenseCategory;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VariableExpenseCategoryController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $tenant = app(Tenant::class);
        $category = $tenant->variableExpenseCategories()->create($request->only('name'));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'variable_expense_category',
            targetId: $category->id,
            action: 'variable_expense_category.created',
            payload: ['name' => $category->name],
            tenantId: $tenant->id,
        );

        if ($request->wantsJson()) {
            return response()->json(['id' => $category->id, 'name' => $category->name], 201);
        }

        return back(fallback: route('variable-expenses.index'))
            ->with('reopen_categories', true)
            ->with('status', 'Categoría creada.');
    }

    public function update(Request $request, VariableExpenseCategory $variableExpenseCategory): RedirectResponse
    {
        $this->authorizeCategory($variableExpenseCategory);
        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $variableExpenseCategory->update($request->only('name'));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'variable_expense_category',
            targetId: $variableExpenseCategory->id,
            action: 'variable_expense_category.updated',
            payload: ['name' => $variableExpenseCategory->name],
            tenantId: $variableExpenseCategory->tenant_id,
        );

        return back(fallback: route('variable-expenses.index'))
            ->with('reopen_categories', true)
            ->with('status', 'Categoría actualizada.');
    }

    public function destroy(VariableExpenseCategory $variableExpenseCategory): RedirectResponse
    {
        $this->authorizeCategory($variableExpenseCategory);

        if ($variableExpenseCategory->variableExpenses()->exists()) {
            return back(fallback: route('variable-expenses.index'))
                ->with('reopen_categories', true)
                ->with('category_error', "No se puede eliminar «{$variableExpenseCategory->name}» porque tiene gastos asignados.");
        }

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'variable_expense_category',
            targetId: $variableExpenseCategory->id,
            action: 'variable_expense_category.deleted',
            payload: ['name' => $variableExpenseCategory->name],
            tenantId: $variableExpenseCategory->tenant_id,
        );

        $variableExpenseCategory->delete();

        return back(fallback: route('variable-expenses.index'))
            ->with('reopen_categories', true)
            ->with('status', 'Categoría eliminada.');
    }

    private function authorizeCategory(VariableExpenseCategory $variableExpenseCategory): void
    {
        abort_unless($variableExpenseCategory->tenant_id === app(Tenant::class)->id, 403);
    }
}

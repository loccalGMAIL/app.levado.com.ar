<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVariableExpenseRequest;
use App\Http\Requests\UpdateVariableExpenseRequest;
use App\Models\Tenant;
use App\Models\VariableExpense;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VariableExpenseController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $sortable = ['name', 'amount', 'expense_date'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $filtered = $tenant->variableExpenses()
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('category'), fn ($q, $categoryId) => $q->where('variable_expense_category_id', $categoryId))
            ->when(request('supplier'), fn ($q, $supplierId) => $q->where('supplier_id', $supplierId))
            ->between(request('from'), request('to'));

        $totalPeriod = (clone $filtered)->sum('amount');

        $variableExpenses = $filtered
            ->with(['category', 'supplier'])
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('expense_date')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        $categories = $tenant->variableExpenseCategories()->orderBy('name')->get();

        // Todos, no sólo los activos: el alta filtra a activos, pero la edición debe poder
        // mostrar un proveedor ya dado de baja sin perderlo, y el filtro debe poder acotar
        // gastos históricos de un proveedor inactivo.
        $suppliers = $tenant->suppliers()->orderBy('name')->get();
        $showCategories = session('reopen_categories', false);

        return view('variable-expenses.index', compact('variableExpenses', 'totalPeriod', 'categories', 'suppliers', 'showCategories'));
    }

    public function store(StoreVariableExpenseRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $variableExpense = $tenant->variableExpenses()->create($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'variable_expense',
            targetId: $variableExpense->id,
            action: 'variable_expense.created',
            payload: ['name' => $variableExpense->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('variable-expenses.index'))->with('status', 'Gasto variable creado.');
    }

    public function update(UpdateVariableExpenseRequest $request, VariableExpense $variableExpense): RedirectResponse
    {
        $this->authorize('update', $variableExpense);

        $variableExpense->update($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'variable_expense',
            targetId: $variableExpense->id,
            action: 'variable_expense.updated',
            payload: ['name' => $variableExpense->name],
            tenantId: $variableExpense->tenant_id,
        );

        return back(fallback: route('variable-expenses.index'))->with('status', 'Gasto variable actualizado.');
    }

    public function destroy(VariableExpense $variableExpense): RedirectResponse
    {
        $this->authorize('delete', $variableExpense);

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'variable_expense',
            targetId: $variableExpense->id,
            action: 'variable_expense.deleted',
            payload: ['name' => $variableExpense->name],
            tenantId: $variableExpense->tenant_id,
        );

        $variableExpense->delete();

        return back(fallback: route('variable-expenses.index'))->with('status', 'Gasto variable eliminado.');
    }
}

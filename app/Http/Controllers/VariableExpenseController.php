<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVariableExpenseRequest;
use App\Http\Requests\UpdateVariableExpenseRequest;
use App\Models\Tenant;
use App\Models\VariableExpense;
use App\Services\AdminActivityRecorder;
use App\Services\ReceiptStorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariableExpenseController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly ReceiptStorer $storer,
    ) {}

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
        $data = $request->safe()->except(['receipt', 'receipt_image_path']);

        // Un archivo nuevo se guarda acá; un path ya vino del scan, con el
        // archivo en disco. El elseif es lo que evita guardarlo dos veces.
        if ($request->hasFile('receipt')) {
            $data['receipt_image_path'] = $this->storer->store($request->file('receipt'), $this->folderFor($tenant));
        } elseif (filled($request->input('receipt_image_path'))) {
            $data['receipt_image_path'] = $this->storer->safePath($request->input('receipt_image_path'), $this->folderFor($tenant));
        }

        $variableExpense = $tenant->variableExpenses()->create($data);

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

        $data = $request->safe()->except('receipt');
        $previousPath = $variableExpense->receipt_image_path;

        if ($request->hasFile('receipt')) {
            $data['receipt_image_path'] = $this->storer->store($request->file('receipt'), $this->folderFor($variableExpense->tenant));
        }

        $variableExpense->update($data);

        if ($request->hasFile('receipt')) {
            $this->storer->delete($previousPath);
        }

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

        $receiptPath = $variableExpense->receipt_image_path;

        $variableExpense->delete();

        $this->storer->delete($receiptPath);

        return back(fallback: route('variable-expenses.index'))->with('status', 'Gasto variable eliminado.');
    }

    public function receipt(VariableExpense $variableExpense): StreamedResponse
    {
        $this->authorize('view', $variableExpense);

        abort_if(
            blank($variableExpense->receipt_image_path)
                || ! Storage::disk('local')->exists($variableExpense->receipt_image_path),
            404,
        );

        // Única vía de acceso: el archivo vive en el disco privado, así que no
        // hay URL que sirva sin pasar por la autorización de arriba.
        return Storage::disk('local')->response($variableExpense->receipt_image_path);
    }

    private function folderFor(Tenant $tenant): string
    {
        return "variable-expenses/{$tenant->id}";
    }
}

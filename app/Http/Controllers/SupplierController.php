<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $sortable = ['name'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $suppliers = $tenant->suppliers()
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function store(StoreSupplierRequest $request): RedirectResponse|JsonResponse
    {
        $tenant = app(Tenant::class);
        $supplier = $tenant->suppliers()->create($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'supplier',
            targetId: $supplier->id,
            action: 'supplier.created',
            payload: ['name' => $supplier->name],
            tenantId: $tenant->id,
        );

        if ($request->wantsJson()) {
            return response()->json(['id' => $supplier->id, 'name' => $supplier->name], 201);
        }

        return back()->with('status', 'Proveedor creado.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'supplier',
            targetId: $supplier->id,
            action: 'supplier.updated',
            payload: ['name' => $supplier->name],
            tenantId: $supplier->tenant_id,
        );

        return back(fallback: route('suppliers.index'))->with('status', 'Proveedor actualizado.');
    }

    public function toggleActive(Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update(['active' => ! $supplier->active]);
        $action = $supplier->active ? 'supplier.activated' : 'supplier.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'supplier',
            targetId: $supplier->id,
            action: $action,
            payload: ['name' => $supplier->name],
            tenantId: $supplier->tenant_id,
        );

        $label = $supplier->active ? 'activado' : 'desactivado';

        return back()->with('status', "Proveedor {$label}.");
    }
}

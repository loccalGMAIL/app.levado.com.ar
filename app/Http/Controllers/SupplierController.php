<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $tenant = app(Tenant::class);
        $suppliers = $tenant->suppliers()->orderBy('name')->get();

        return view('suppliers.index', compact('suppliers'));
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        app(Tenant::class)->suppliers()->create($request->validated());

        return back()->with('status', 'Proveedor creado.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizeSupplier($supplier);

        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')->with('status', 'Proveedor actualizado.');
    }

    public function toggleActive(Supplier $supplier): RedirectResponse
    {
        $this->authorizeSupplier($supplier);

        $supplier->update(['active' => ! $supplier->active]);

        $label = $supplier->active ? 'activado' : 'desactivado';

        return back()->with('status', "Proveedor {$label}.");
    }

    private function authorizeSupplier(Supplier $supplier): void
    {
        abort_unless($supplier->tenant_id === app(Tenant::class)->id, 403);
    }
}

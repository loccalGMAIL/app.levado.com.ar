<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePackagingRequest;
use App\Http\Requests\UpdatePackagingRequest;
use App\Models\Packaging;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PackagingController extends Controller
{
    public function index(): View
    {
        $tenant = app(Tenant::class);
        $packagings = $tenant->packagings()
            ->with('supplier')
            ->orderBy('name')
            ->get();
        $suppliers = $tenant->suppliers()->where('active', true)->orderBy('name')->get();

        return view('packaging.index', compact('packagings', 'suppliers'));
    }

    public function store(StorePackagingRequest $request): RedirectResponse
    {
        $packaging = app(Tenant::class)->packagings()->create($request->validated());

        $packaging->priceLogs()->create([
            'cost_per_unit' => $packaging->cost_per_unit,
            'recorded_at' => now(),
        ]);

        return redirect()->route('packaging.index')->with('status', 'Envase creado.');
    }

    public function update(UpdatePackagingRequest $request, Packaging $packaging): RedirectResponse
    {
        $this->authorizePackaging($packaging);

        $data = $request->validated();

        if ((float) $packaging->cost_per_unit !== (float) $data['cost_per_unit']) {
            $packaging->priceLogs()->create([
                'cost_per_unit' => $data['cost_per_unit'],
                'recorded_at' => now(),
            ]);
        }

        $packaging->update($data);

        return redirect()->route('packaging.index')->with('status', 'Envase actualizado.');
    }

    public function toggleActive(Packaging $packaging): RedirectResponse
    {
        $this->authorizePackaging($packaging);

        $packaging->update(['active' => ! $packaging->active]);

        $label = $packaging->active ? 'activado' : 'desactivado';

        return back()->with('status', "Envase {$label}.");
    }

    private function authorizePackaging(Packaging $packaging): void
    {
        abort_unless($packaging->tenant_id === app(Tenant::class)->id, 403);
    }
}

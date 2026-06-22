<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePackagingRequest;
use App\Http\Requests\UpdatePackagingRequest;
use App\Models\Packaging;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostPropagator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PackagingController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly RecipeCostPropagator $propagator,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $sortable = ['name', 'cost_per_unit'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $packagings = $tenant->packagings()
            ->with('supplier')
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();
        $suppliers = $tenant->suppliers()->active()->orderBy('name')->get();

        return view('packaging.index', compact('packagings', 'suppliers'));
    }

    public function store(StorePackagingRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();

        if (! empty($data['subdivisions'])) {
            $data['cost_per_package'] = $data['cost_per_unit'];
            $data['cost_per_unit'] = $data['cost_per_unit'] / $data['subdivisions'];
        }

        $packaging = $tenant->packagings()->create($data);

        $packaging->priceLogs()->create([
            'cost_per_unit' => $packaging->cost_per_unit,
            'recorded_at' => now(),
        ]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'packaging',
            targetId: $packaging->id,
            action: 'packaging.created',
            payload: ['name' => $packaging->name],
            tenantId: $tenant->id,
        );

        return redirect()->route('packaging.index')->with('status', 'Envase creado.');
    }

    public function update(UpdatePackagingRequest $request, Packaging $packaging): RedirectResponse
    {
        $this->authorize('update', $packaging);

        $data = $request->validated();

        if (! empty($data['subdivisions'])) {
            $data['cost_per_package'] = $data['cost_per_unit'];
            $data['cost_per_unit'] = $data['cost_per_unit'] / $data['subdivisions'];
        } else {
            $data['cost_per_package'] = null;
        }

        $costChanged = (float) $packaging->cost_per_unit !== (float) $data['cost_per_unit'];

        if ($costChanged) {
            $packaging->priceLogs()->create([
                'cost_per_unit' => $data['cost_per_unit'],
                'recorded_at' => now(),
            ]);
        }

        $packaging->update($data);

        if ($costChanged) {
            $this->propagator->propagateFromPackaging($packaging->id);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'packaging',
            targetId: $packaging->id,
            action: 'packaging.updated',
            payload: ['name' => $packaging->name],
            tenantId: $packaging->tenant_id,
        );

        return redirect()->route('packaging.index')->with('status', 'Envase actualizado.');
    }

    public function toggleActive(Packaging $packaging): RedirectResponse
    {
        $this->authorize('update', $packaging);

        $packaging->update(['active' => ! $packaging->active]);
        $action = $packaging->active ? 'packaging.activated' : 'packaging.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'packaging',
            targetId: $packaging->id,
            action: $action,
            payload: ['name' => $packaging->name],
            tenantId: $packaging->tenant_id,
        );

        $label = $packaging->active ? 'activado' : 'desactivado';

        return back()->with('status', "Envase {$label}.");
    }
}

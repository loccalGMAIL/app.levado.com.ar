<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLaborTypeRequest;
use App\Http\Requests\UpdateLaborTypeRequest;
use App\Models\LaborType;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostPropagator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LaborTypeController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly RecipeCostPropagator $propagator,
    ) {}

    public function index(): View
    {
        $laborTypes = app(Tenant::class)->laborTypes()
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->where('active', true))
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('labor-types.index', compact('laborTypes'));
    }

    public function store(StoreLaborTypeRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $laborType = $tenant->laborTypes()->create($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'labor_type',
            targetId: $laborType->id,
            action: 'labor_type.created',
            payload: ['name' => $laborType->name],
            tenantId: $tenant->id,
        );

        return redirect()->route('labor-types.index')->with('status', 'Tipo de mano de obra creado.');
    }

    public function update(UpdateLaborTypeRequest $request, LaborType $laborType): RedirectResponse
    {
        $this->authorizeLaborType($laborType);

        $data = $request->validated();
        $rateChanged = (float) $laborType->hourly_rate !== (float) ($data['hourly_rate'] ?? $laborType->hourly_rate);

        $laborType->update($data);

        if ($rateChanged) {
            $this->propagator->propagateFromLaborType($laborType->id);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'labor_type',
            targetId: $laborType->id,
            action: 'labor_type.updated',
            payload: ['name' => $laborType->name],
            tenantId: $laborType->tenant_id,
        );

        return redirect()->route('labor-types.index')->with('status', 'Tipo de mano de obra actualizado.');
    }

    public function toggleActive(LaborType $laborType): RedirectResponse
    {
        $this->authorizeLaborType($laborType);

        $laborType->update(['active' => ! $laborType->active]);
        $action = $laborType->active ? 'labor_type.activated' : 'labor_type.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'labor_type',
            targetId: $laborType->id,
            action: $action,
            payload: ['name' => $laborType->name],
            tenantId: $laborType->tenant_id,
        );

        $label = $laborType->active ? 'activado' : 'desactivado';

        return back()->with('status', "Tipo de mano de obra {$label}.");
    }

    private function authorizeLaborType(LaborType $laborType): void
    {
        abort_unless($laborType->tenant_id === app(Tenant::class)->id, 403);
    }
}

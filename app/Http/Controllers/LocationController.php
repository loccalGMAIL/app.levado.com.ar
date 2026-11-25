<?php

namespace App\Http\Controllers;

use App\Enums\LocationKind;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $locations = $tenant->locations()->orderByDesc('is_default')->orderBy('name')->get();

        return view('locations.index', compact('locations'));
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();
        $kind = LocationKind::tryFrom((string) ($data['kind'] ?? '')) ?? LocationKind::Store;

        $isDefault = (bool) ($data['is_default'] ?? false);

        if ($kind === LocationKind::ProofingChamber) {
            if ($isDefault) {
                return $this->kindError('La cámara de leudado no puede ser la sucursal principal.');
            }

            if ($this->otherChamberExists($tenant)) {
                return $this->kindError('El negocio ya tiene una cámara de leudado.');
            }
        }

        if ($isDefault) {
            $tenant->locations()->update(['is_default' => false]);
        }

        if ($kind === LocationKind::Store && $tenant->locations()->stores()->count() === 0) {
            $isDefault = true;
        }

        $location = $tenant->locations()->create(array_merge($data, ['kind' => $kind, 'is_default' => $isDefault]));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'location',
            targetId: $location->id,
            action: 'location.created',
            payload: ['name' => $location->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('locations.index'))->with('status', 'Sucursal creada.');
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $this->authorize('update', $location);

        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);
        $kind = LocationKind::tryFrom((string) ($data['kind'] ?? '')) ?? $location->kind;

        if ($kind !== $location->kind && $location->hasStockMovements()) {
            return $this->kindError('No se puede cambiar el tipo: la ubicación ya tiene movimientos de stock.');
        }

        if ($kind === LocationKind::ProofingChamber) {
            if ($isDefault || $location->is_default) {
                return $this->kindError('La cámara de leudado no puede ser la sucursal principal.');
            }

            if ($this->otherChamberExists(app(Tenant::class), $location)) {
                return $this->kindError('El negocio ya tiene una cámara de leudado.');
            }
        }

        if ($isDefault) {
            app(Tenant::class)->locations()->where('id', '!=', $location->id)->update(['is_default' => false]);
        }

        $location->update(array_merge($data, ['kind' => $kind, 'is_default' => $isDefault]));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'location',
            targetId: $location->id,
            action: 'location.updated',
            payload: ['name' => $location->name],
            tenantId: $location->tenant_id,
        );

        return back(fallback: route('locations.index'))->with('status', 'Sucursal actualizada.');
    }

    public function toggleActive(Location $location): RedirectResponse
    {
        $this->authorize('update', $location);

        if ($location->is_default && $location->active) {
            return back()->with('error', 'No se puede desactivar la sucursal principal.');
        }

        $location->update(['active' => ! $location->active]);
        $action = $location->active ? 'location.activated' : 'location.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'location',
            targetId: $location->id,
            action: $action,
            payload: ['name' => $location->name],
            tenantId: $location->tenant_id,
        );

        $label = $location->active ? 'activada' : 'desactivada';

        return back()->with('status', "Sucursal {$label}.");
    }

    /** Una sola cámara por negocio (varias cámaras queda fuera de alcance). */
    private function otherChamberExists(Tenant $tenant, ?Location $except = null): bool
    {
        return $tenant->locations()
            ->where('kind', LocationKind::ProofingChamber->value)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))
            ->exists();
    }

    private function kindError(string $message): RedirectResponse
    {
        return back(fallback: route('locations.index'))->withErrors(['kind' => $message])->withInput();
    }
}

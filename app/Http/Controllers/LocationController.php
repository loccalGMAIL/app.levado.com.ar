<?php

namespace App\Http\Controllers;

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

        $isDefault = (bool) ($data['is_default'] ?? false);

        if ($isDefault) {
            $tenant->locations()->update(['is_default' => false]);
        }

        if ($tenant->locations()->count() === 0) {
            $isDefault = true;
        }

        $location = $tenant->locations()->create(array_merge($data, ['is_default' => $isDefault]));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'location',
            targetId: $location->id,
            action: 'location.created',
            payload: ['name' => $location->name],
            tenantId: $tenant->id,
        );

        return redirect()->route('locations.index')->with('status', 'Sucursal creada.');
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $this->authorizeLocation($location);

        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);

        if ($isDefault) {
            app(Tenant::class)->locations()->where('id', '!=', $location->id)->update(['is_default' => false]);
        }

        $location->update(array_merge($data, ['is_default' => $isDefault]));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'location',
            targetId: $location->id,
            action: 'location.updated',
            payload: ['name' => $location->name],
            tenantId: $location->tenant_id,
        );

        return redirect()->route('locations.index')->with('status', 'Sucursal actualizada.');
    }

    public function toggleActive(Location $location): RedirectResponse
    {
        $this->authorizeLocation($location);

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

    private function authorizeLocation(Location $location): void
    {
        abort_unless($location->tenant_id === app(Tenant::class)->id, 403);
    }
}

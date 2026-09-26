<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryPersonRequest;
use App\Http\Requests\UpdateDeliveryPersonRequest;
use App\Models\DeliveryPerson;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeliveryPersonController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $deliveryPeople = $tenant->deliveryPeople()->orderBy('name')->get();

        return view('reparto.repartidores', compact('deliveryPeople'));
    }

    public function store(StoreDeliveryPersonRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $deliveryPerson = $tenant->deliveryPeople()->create($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'delivery_person',
            targetId: $deliveryPerson->id,
            action: 'delivery_person.created',
            payload: ['name' => $deliveryPerson->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('reparto.repartidores.index'))->with('status', 'Repartidor creado.');
    }

    public function update(UpdateDeliveryPersonRequest $request, DeliveryPerson $deliveryPerson): RedirectResponse
    {
        $this->authorize('update', $deliveryPerson);

        $deliveryPerson->update($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'delivery_person',
            targetId: $deliveryPerson->id,
            action: 'delivery_person.updated',
            payload: ['name' => $deliveryPerson->name],
            tenantId: $deliveryPerson->tenant_id,
        );

        return back(fallback: route('reparto.repartidores.index'))->with('status', 'Repartidor actualizado.');
    }

    public function toggleActive(DeliveryPerson $deliveryPerson): RedirectResponse
    {
        $this->authorize('update', $deliveryPerson);

        $deliveryPerson->update(['active' => ! $deliveryPerson->active]);
        $action = $deliveryPerson->active ? 'delivery_person.activated' : 'delivery_person.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'delivery_person',
            targetId: $deliveryPerson->id,
            action: $action,
            payload: ['name' => $deliveryPerson->name],
            tenantId: $deliveryPerson->tenant_id,
        );

        $label = $deliveryPerson->active ? 'activado' : 'desactivado';

        return back()->with('status', "Repartidor {$label}.");
    }
}

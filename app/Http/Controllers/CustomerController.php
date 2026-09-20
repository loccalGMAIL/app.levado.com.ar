<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $customers = $tenant->customers()->with('deliveryPerson')->orderBy('name')->get();
        $deliveryPeople = $tenant->deliveryPeople()->active()->orderBy('name')->get();

        return view('customers.index', compact('customers', 'deliveryPeople'));
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $customer = $tenant->customers()->create($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'customer',
            targetId: $customer->id,
            action: 'customer.created',
            payload: ['name' => $customer->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('customers.index'))->with('status', 'Cliente creado.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'customer',
            targetId: $customer->id,
            action: 'customer.updated',
            payload: ['name' => $customer->name],
            tenantId: $customer->tenant_id,
        );

        return back(fallback: route('customers.index'))->with('status', 'Cliente actualizado.');
    }

    public function toggleActive(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update(['active' => ! $customer->active]);
        $action = $customer->active ? 'customer.activated' : 'customer.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'customer',
            targetId: $customer->id,
            action: $action,
            payload: ['name' => $customer->name],
            tenantId: $customer->tenant_id,
        );

        $label = $customer->active ? 'activado' : 'desactivado';

        return back()->with('status', "Cliente {$label}.");
    }
}

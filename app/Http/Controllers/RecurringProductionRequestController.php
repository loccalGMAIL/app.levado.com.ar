<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRecurringProductionRequestRequest;
use App\Models\Product;
use App\Models\RecurringProductionRequest;
use App\Models\Tenant;
use App\Services\RecurringProductionRequestMaterializer;
use App\Services\RecurringProductionRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Administración de los pedidos recurrentes ya creados (nacen únicamente
 * desde el tilde "Se repite" al cargar un pedido — ver
 * ProductionRequestController::store()). Acá sólo se ven, editan, pausan o
 * se generan a demanda; no hay alta nueva desde esta pantalla.
 */
class RecurringProductionRequestController extends Controller
{
    public function __construct(
        private readonly RecurringProductionRequestService $recurring,
        private readonly RecurringProductionRequestMaterializer $materializer,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);

        $recurring = $tenant->recurringProductionRequests()
            ->with(['destination', 'lines.product'])
            ->withCount('instances')
            ->orderByDesc('active')
            ->orderBy('id')
            ->get();

        $products = $tenant->products()->producible()->orderBy('name')->get(['id', 'name', 'unit'])
            ->map(fn (Product $product) => ['id' => $product->id, 'name' => $product->name, 'unit' => $product->unit->short()]);

        return view('production-orders.recurring', compact('recurring', 'products', 'tenant'));
    }

    public function update(UpdateRecurringProductionRequestRequest $request, RecurringProductionRequest $recurringProductionRequest): RedirectResponse
    {
        $this->authorize('update', $recurringProductionRequest);

        $this->recurring->update($recurringProductionRequest, $request->validated());

        return back(fallback: route('production-requests.recurring.index'))->with('status', 'Pedido recurrente actualizado.');
    }

    public function toggleActive(RecurringProductionRequest $recurringProductionRequest): RedirectResponse
    {
        $this->authorize('update', $recurringProductionRequest);

        $this->recurring->toggleActive($recurringProductionRequest);
        $label = $recurringProductionRequest->active ? 'reanudado' : 'pausado';

        return back(fallback: route('production-requests.recurring.index'))->with('status', "Pedido recurrente {$label}.");
    }

    /** Salta el throttle de materializeIfDue() — escape manual. */
    public function generateNow(): RedirectResponse
    {
        $generated = $this->materializer->materialize(app(Tenant::class));

        return back(fallback: route('production-requests.recurring.index'))
            ->with('status', $generated > 0 ? "{$generated} pedido(s) generado(s)." : 'No había nada nuevo para generar.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ProductionOrderStatus;
use App\Http\Requests\StoreProductionOrderRequest;
use App\Models\ProductionOrder;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\ProductionOrderService;
use App\Services\RecurringProductionRequestMaterializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $orders,
        private readonly AdminActivityRecorder $recorder,
        private readonly RecurringProductionRequestMaterializer $materializer,
    ) {}

    public function index(Request $request): View
    {
        $tenant = app(Tenant::class);

        // Sin cron: cada vez que alguien abre Órdenes se completan los
        // pedidos recurrentes que falten hasta el horizonte (materializeIfDue
        // throttlea a una corrida por hora por negocio, nunca propaga).
        $this->materializer->materializeIfDue($tenant);

        $query = $tenant->productionOrders()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('scheduled_for', $request->string('date')))
            ->with('user')
            ->withCount('productionOrderRequests')
            // Badge 🔁: la orden tiene al menos un pedido generado por un
            // recurrente. Sin query extra — es el mismo where sobre la
            // relación que ya cuenta arriba, sólo con otro alias.
            ->withCount(['productionOrderRequests as recurring_requests_count' => fn ($q) => $q->whereNotNull('recurring_production_request_id')]);

        // Columnas ordenables de <x-sortable-th> — whitelist explícita, el
        // nombre de columna nunca viene de input directo al orderBy().
        // Default: número de orden descendente (el más nuevo primero, mismo
        // efecto que el ->latest() fijo que reemplaza).
        $sortable = ['number', 'scheduled_for', 'type', 'production_order_requests_count', 'status'];
        $sort = in_array($request->string('sort')->toString(), $sortable, true) ? $request->string('sort')->toString() : 'number';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'scheduled_for' => $query->orderBy('scheduled_for', $dir)->orderByDesc('id'),
            'type' => $query->orderBy('type', $dir)->orderByDesc('id'),
            'production_order_requests_count' => $query->orderBy('production_order_requests_count', $dir)->orderByDesc('id'),
            'status' => $query->orderBy('status', $dir)->orderByDesc('id'),
            default => $query->orderBy('number', $dir)->orderByDesc('id'),
        };

        $orders = $query->paginate(20)->withQueryString();

        // Para el modal "+ Nuevo pedido": mismos datos que ya junta show(),
        // acá vive el punto de entrada nuevo (crear el pedido sin abrir
        // ninguna orden primero).
        [$locations, $customers, $products] = $this->orders->destinationAndCatalogData($tenant);

        return view('production-orders.index', compact('orders', 'locations', 'customers', 'products'));
    }

    public function store(StoreProductionOrderRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);

        $order = $this->orders->createOrder($tenant, [
            ...$request->validated(),
            'location_id' => $tenant->defaultLocation()->id,
            'status' => ProductionOrderStatus::Draft->value,
            'is_template' => false,
            'user_id' => $request->user()->id,
        ]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'production_order',
            targetId: $order->id,
            action: 'production_order.created',
            payload: ['type' => $order->type->value],
            tenantId: $tenant->id,
        );

        return redirect()->route('production-orders.show', $order)->with('status', 'Orden creada.');
    }

    public function show(ProductionOrder $productionOrder): View
    {
        $this->authorize('view', $productionOrder);

        $productionOrder->load(['productionOrderRequests.destination', 'productionOrderRequests.lines.product', 'user']);

        [$locations, $customers, $products] = $this->orders->destinationAndCatalogData(app(Tenant::class));

        return view('production-orders.show', compact('productionOrder', 'customers', 'locations', 'products'));
    }

    public function preview(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('view', $productionOrder);

        // load('location'): ProductionOrderService::preview() lee $order->location;
        // sin esto es un lazy load que preventLazyLoading sólo loguea (no revienta
        // fuera de tests), y ningún test pegaba a este endpoint hasta ahora.
        $data = $this->orders->preview($productionOrder->load('location'));

        // "Total a producir" se pinta con Alpine desde este mismo payload —
        // show() ya no calcula $aggregated aparte (una consulta con dos
        // eager loads menos por render).
        $data['aggregated'] = $this->orders->aggregate($productionOrder)->map(fn (array $entry) => [
            'product_id' => $entry['product']->id,
            'name' => $entry['product']->name,
            'unit' => $entry['product']->unit->short(),
            'quantity' => $entry['quantity'],
        ]);

        return response()->json($data);
    }

    public function transition(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $this->authorize('update', $productionOrder);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ProductionOrderStatus::class)],
        ]);

        $this->orders->transitionTo($productionOrder, ProductionOrderStatus::from($data['status']), $request->user());

        return back(fallback: route('production-orders.show', $productionOrder))->with('status', 'Estado actualizado.');
    }

    public function produce(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $this->authorize('update', $productionOrder);

        $this->orders->produce($productionOrder, $request->user());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'production_order',
            targetId: $productionOrder->id,
            action: 'production_order.produced',
            payload: [],
            tenantId: $productionOrder->tenant_id,
        );

        return redirect()->route('production-orders.show', $productionOrder)->with('status', 'Orden producida.');
    }

    public function cancel(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $this->authorize('update', $productionOrder);

        $this->orders->cancel($productionOrder, $request->user());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'production_order',
            targetId: $productionOrder->id,
            action: 'production_order.cancelled',
            payload: [],
            tenantId: $productionOrder->tenant_id,
        );

        return back(fallback: route('production-orders.show', $productionOrder))->with('status', 'Orden anulada.');
    }
}

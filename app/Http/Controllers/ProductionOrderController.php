<?php

namespace App\Http\Controllers;

use App\Enums\ProductionOrderStatus;
use App\Http\Requests\StoreProductionOrderRequest;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\ProductionOrderDuplicator;
use App\Services\ProductionOrderService;
use App\Services\RecurringProductionRequestMaterializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $orders,
        private readonly ProductionOrderDuplicator $duplicator,
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

        $orders = $tenant->productionOrders()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('scheduled_for', $request->string('date')))
            ->with('user')
            ->withCount('productionOrderRequests')
            // Badge 🔁: la orden tiene al menos un pedido generado por un
            // recurrente. Sin query extra — es el mismo where sobre la
            // relación que ya cuenta arriba, sólo con otro alias.
            ->withCount(['productionOrderRequests as recurring_requests_count' => fn ($q) => $q->whereNotNull('recurring_production_request_id')])
            ->latest('scheduled_for')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Para el modal "+ Nuevo pedido": mismos datos que ya junta show(),
        // acá vive el punto de entrada nuevo (crear el pedido sin abrir
        // ninguna orden primero).
        [$locations, $deliveryPeople, $products] = $this->destinationAndCatalogData($tenant);

        return view('production-orders.index', compact('orders', 'locations', 'deliveryPeople', 'products'));
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

        [$locations, $deliveryPeople, $products] = $this->destinationAndCatalogData(app(Tenant::class));

        return view('production-orders.show', compact('productionOrder', 'deliveryPeople', 'locations', 'products'));
    }

    /**
     * Sucursales/repartidores activos + el catálogo liviano de artículos
     * producibles (id/nombre/unidad, no el modelo completo) — lo que
     * necesitan tanto el detalle de una orden como el modal "+ Nuevo
     * pedido". Con el gate producible invertido son ~195 artículos; viaja
     * una sola vez por página, compartido por referencia entre las grillas.
     *
     * @return array{0: Collection, 1: Collection, 2: Collection}
     */
    private function destinationAndCatalogData(Tenant $tenant): array
    {
        $locations = $tenant->locations()->active()->orderBy('name')->get();
        $deliveryPeople = $tenant->deliveryPeople()->active()->orderBy('name')->get();
        $products = $tenant->products()->producible()->orderBy('name')->get(['id', 'name', 'unit'])
            ->map(fn (Product $product) => ['id' => $product->id, 'name' => $product->name, 'unit' => $product->unit->short()]);

        return [$locations, $deliveryPeople, $products];
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

    public function deliverySheet(ProductionOrder $productionOrder): View
    {
        $this->authorize('view', $productionOrder);

        $productionOrder->load(['productionOrderRequests.destination', 'productionOrderRequests.lines.product']);

        return view('production-orders.delivery-sheet', compact('productionOrder'));
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

    public function duplicate(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $this->authorize('view', $productionOrder);

        $data = $request->validate(['scheduled_for' => ['nullable', 'date']]);

        $copy = $this->duplicator->duplicate(
            $productionOrder,
            $request->user(),
            scheduledFor: $data['scheduled_for'] ?? now()->toDateString(),
        );

        return redirect()->route('production-orders.show', $copy)->with('status', 'Orden duplicada.');
    }

    public function saveAsTemplate(Request $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $this->authorize('view', $productionOrder);

        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $this->duplicator->duplicate($productionOrder, $request->user(), templateName: $data['name']);

        return back(fallback: route('production-orders.show', $productionOrder))->with('status', 'Guardada como plantilla.');
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

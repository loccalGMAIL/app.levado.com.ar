<?php

namespace App\Http\Controllers;

use App\Enums\ProductionOrderStatus;
use App\Http\Requests\StoreProductionOrderRequest;
use App\Models\ProductionOrder;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\ProductionOrderDuplicator;
use App\Services\ProductionOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionOrderService $orders,
        private readonly ProductionOrderDuplicator $duplicator,
        private readonly AdminActivityRecorder $recorder,
    ) {}

    public function index(Request $request): View
    {
        $tenant = app(Tenant::class);

        $orders = $tenant->productionOrders()
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('scheduled_for', $request->string('date')))
            ->with('user')
            ->withCount('productionOrderRequests')
            ->latest('scheduled_for')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('production-orders.index', compact('orders'));
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

        $tenant = app(Tenant::class);
        $aggregated = $this->orders->aggregate($productionOrder);
        $deliveryPeople = $tenant->deliveryPeople()->active()->orderBy('name')->get();
        $locations = $tenant->locations()->active()->orderBy('name')->get();
        $products = $tenant->products()->producible()->orderBy('name')->get();

        return view('production-orders.show', compact('productionOrder', 'aggregated', 'deliveryPeople', 'locations', 'products'));
    }

    public function preview(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('view', $productionOrder);

        return response()->json($this->orders->preview($productionOrder));
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

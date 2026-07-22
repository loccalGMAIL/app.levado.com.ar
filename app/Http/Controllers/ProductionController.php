<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\StoreProductionRequest;
use App\Models\Production;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function __construct(
        private readonly ProductionService $productions,
        private readonly AdminActivityRecorder $recorder,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);

        $productions = $tenant->productions()
            ->with(['product', 'user'])
            ->latest('produced_at')
            ->latest('id')
            ->paginate(20);

        return view('production.index', compact('productions'));
    }

    public function create(): View
    {
        $tenant = app(Tenant::class);

        // Solo elaborados activos con receta: son los únicos que se pueden producir.
        $products = $tenant->products()
            ->active()
            ->where('type', ProductType::Manufactured->value)
            ->whereNotNull('recipe_id')
            ->with('recipe')
            ->orderBy('name')
            ->get();

        return view('production.create', compact('products'));
    }

    public function preview(Request $request): JsonResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $product = $tenant->products()->findOrFail($data['product_id']);

        return response()->json($this->productions->preview($product, (float) $data['quantity']));
    }

    public function store(StoreProductionRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $product = $tenant->products()->findOrFail($request->validated('product_id'));

        $production = $this->productions->produce(
            $product,
            (float) $request->validated('quantity'),
            $request->validated('notes'),
            $request->user(),
        );

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'production',
            targetId: $production->id,
            action: 'production.created',
            payload: ['product' => $product->name, 'quantity' => (float) $production->quantity],
            tenantId: $tenant->id,
        );

        return redirect()->route('production.show', $production)->with('status', 'Producción registrada.');
    }

    public function show(Production $production): View
    {
        $this->authorize('view', $production);

        $production->load(['product', 'recipe', 'user']);
        $movements = $production->movements()
            ->with(['ingredient', 'packaging', 'product'])
            ->orderBy('id')
            ->get();

        return view('production.show', compact('production', 'movements'));
    }

    public function cancel(Production $production): RedirectResponse
    {
        $this->authorize('update', $production);

        $this->productions->cancel($production, request()->user());

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'production',
            targetId: $production->id,
            action: 'production.cancelled',
            payload: ['product' => $production->product?->name],
            tenantId: $production->tenant_id,
        );

        return back(fallback: route('production.index'))->with('status', 'Producción anulada.');
    }
}

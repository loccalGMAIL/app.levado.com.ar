<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Requests\StoreStockCountRequest;
use App\Http\Requests\UpdateStockMinRequest;
use App\Models\Ingredient;
use App\Models\Location;
use App\Models\Packaging;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly StockService $stock,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $location = $this->resolveLocation($tenant);
        $type = request('type') === 'packaging' ? 'packaging' : 'ingredient';
        $table = $type === 'ingredient' ? 'ingredients' : 'packagings';

        $itemsQuery = $type === 'ingredient'
            ? $tenant->ingredients()->active()
            : $tenant->packagings()->active();

        $sortable = [
            'name' => "{$table}.name",
            'quantity' => DB::raw('COALESCE(stock_levels.quantity, 0)'),
            'min_quantity' => 'stock_levels.min_quantity',
        ];
        $sort = array_key_exists(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $items = $itemsQuery
            ->leftJoin('stock_levels', function ($join) use ($table, $type, $tenant, $location) {
                $join->on('stock_levels.stockable_id', '=', "{$table}.id")
                    ->where('stock_levels.stockable_type', $type)
                    ->where('stock_levels.tenant_id', $tenant->id)
                    ->where('stock_levels.location_id', $location->id);
            })
            ->select("{$table}.*")
            ->when(request('search'), function ($q, $search) use ($table) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where("{$table}.name", 'like', "%{$escaped}%");
            })
            ->when(
                $sort,
                fn ($q) => $q->orderBy($sortable[$sort], $dir),
                fn ($q) => $q->orderBy($sortable['name'], 'asc')
            )
            ->paginate(20)
            ->withQueryString();

        $levels = StockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $location->id)
            ->where('stockable_type', $type)
            ->whereIn('stockable_id', $items->pluck('id'))
            ->get()
            ->keyBy('stockable_id');

        $locations = $tenant->locations()->active()->orderBy('name')->get();

        return view('stock.index', compact('items', 'levels', 'type', 'location', 'locations'));
    }

    public function show(string $type, int $id): View
    {
        $tenant = app(Tenant::class);
        $item = $this->resolveStockable($type, $id);
        $this->authorize('view', $item);

        $location = $this->resolveLocation($tenant);

        $movements = $item->stockMovements()
            ->where('location_id', $location->id)
            ->with('user')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $level = $this->stock->levelFor($item, $location);

        return view('stock.show', compact('item', 'type', 'movements', 'level', 'location'));
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request, string $type, int $id): RedirectResponse
    {
        $item = $this->resolveStockable($type, $id);
        $this->authorize('update', $item);

        $tenant = app(Tenant::class);
        $movement = $this->stock->registerAdjustment(
            item: $item,
            location: $this->resolveLocation($tenant),
            signedQuantity: (float) $request->validated('quantity'),
            reason: $request->validated('reason'),
            user: $request->user(),
        );

        $this->recordActivity($request->user(), $type, $item, 'stock.adjustment', [
            'quantity' => (float) $movement->quantity,
            'reason' => $movement->reason,
        ]);

        return back(fallback: route('stock.index'))->with('status', 'Ajuste de stock registrado.');
    }

    public function storeCount(StoreStockCountRequest $request, string $type, int $id): RedirectResponse
    {
        $item = $this->resolveStockable($type, $id);
        $this->authorize('update', $item);

        $tenant = app(Tenant::class);
        $movement = $this->stock->applyCount(
            item: $item,
            location: $this->resolveLocation($tenant),
            countedQuantity: (float) $request->validated('counted_quantity'),
            user: $request->user(),
        );

        if ($movement === null) {
            return back(fallback: route('stock.index'))->with('status', 'Recuento sin diferencias: el stock ya estaba al día.');
        }

        $this->recordActivity($request->user(), $type, $item, 'stock.count', [
            'counted_quantity' => (float) $request->validated('counted_quantity'),
            'delta' => (float) $movement->quantity,
        ]);

        return back(fallback: route('stock.index'))->with('status', 'Recuento registrado.');
    }

    /**
     * Edición inline desde los catálogos: recibe el stock absoluto contado y
     * registra la diferencia como recuento (applyCount). Responde JSON para
     * actualizar la celda sin recargar.
     */
    public function updateLevel(StoreStockCountRequest $request, string $type, int $id): JsonResponse
    {
        $item = $this->resolveStockable($type, $id);
        $this->authorize('update', $item);

        $tenant = app(Tenant::class);
        $location = $this->resolveLocation($tenant);

        $movement = $this->stock->applyCount(
            item: $item,
            location: $location,
            countedQuantity: (float) $request->validated('counted_quantity'),
            user: $request->user(),
        );

        if ($movement !== null) {
            $this->recordActivity($request->user(), $type, $item, 'stock.count', [
                'counted_quantity' => (float) $request->validated('counted_quantity'),
                'delta' => (float) $movement->quantity,
            ]);
        }

        $quantity = (float) ($this->stock->levelFor($item, $location)?->quantity ?? 0);

        return response()->json([
            'quantity' => $quantity,
            'quantity_formatted' => number_format($quantity, 2, ',', '.'),
        ]);
    }

    public function updateMin(UpdateStockMinRequest $request, string $type, int $id): RedirectResponse
    {
        $item = $this->resolveStockable($type, $id);
        $this->authorize('update', $item);

        $tenant = app(Tenant::class);
        $minQuantity = $request->validated('min_quantity');

        $this->stock->setMinQuantity(
            item: $item,
            location: $this->resolveLocation($tenant),
            minQuantity: $minQuantity !== null ? (float) $minQuantity : null,
        );

        $this->recordActivity($request->user(), $type, $item, 'stock.min_updated', [
            'min_quantity' => $minQuantity,
        ]);

        return back(fallback: route('stock.index'))->with('status', 'Stock mínimo actualizado.');
    }

    private function resolveStockable(string $type, int $id): Ingredient|Packaging
    {
        $tenant = app(Tenant::class);

        return $type === 'ingredient'
            ? $tenant->ingredients()->findOrFail($id)
            : $tenant->packagings()->findOrFail($id);
    }

    /**
     * Sucursal de trabajo: la elegida por query string (validada contra el tenant)
     * o la default. Hoy la UI no expone selector; queda la costura para sucursales.
     */
    private function resolveLocation(Tenant $tenant): Location
    {
        if (request('location_id')) {
            $location = $tenant->locations()->active()->find(request('location_id'));

            if ($location !== null) {
                return $location;
            }
        }

        return $tenant->defaultLocation();
    }

    private function recordActivity($actor, string $type, Ingredient|Packaging $item, string $action, array $payload): void
    {
        $this->recorder->record(
            actor: $actor,
            targetType: $type,
            targetId: $item->id,
            action: $action,
            payload: $payload + ['name' => $item->name],
            tenantId: $item->tenant_id,
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Http\Requests\StorePackagingRequest;
use App\Http\Requests\UpdatePackagingRequest;
use App\Models\Packaging;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostPropagator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PackagingController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly RecipeCostPropagator $propagator,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $locationId = $tenant->defaultLocation()->id;

        $packagings = $tenant->packagings()
            ->with('supplier')
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->tap(fn (Builder $q) => $this->sortListing($q, [
                'name' => 'name',
                'brand' => 'brand',
                'supplier' => Supplier::select('name')->whereColumn('suppliers.id', 'packagings.supplier_id'),
                'cost_per_package' => 'cost_per_package',
                'cost_per_unit' => 'cost_per_unit',
                'stock' => fn (Builder $q, string $dir) => StockLevel::orderCatalogByQuantity($q, 'packagings', CatalogItemType::Packaging, $locationId, $dir),
                'active' => 'active',
            ], fn (Builder $q) => $q->orderByDesc('active')->orderBy('name'), 'packagings.id'))
            ->paginate($this->perPage())
            ->withQueryString();
        // Todos, no sólo los activos: el modal de alta filtra a activos, pero el de edición
        // debe poder mostrar un proveedor ya dado de baja o el select caería en «Ninguno»
        // y guardar borraría el proveedor en silencio.
        $suppliers = $tenant->suppliers()->orderBy('name')->get();

        $stockLevels = StockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $locationId)
            ->where('stockable_type', CatalogItemType::Packaging->value)
            ->whereIn('stockable_id', $packagings->pluck('id'))
            ->get()
            ->keyBy('stockable_id');

        return view('packaging.index', compact('packagings', 'suppliers', 'stockLevels'));
    }

    public function store(StorePackagingRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();

        if (! empty($data['subdivisions'])) {
            $data['cost_per_package'] = $data['cost_per_unit'];
            $data['cost_per_unit'] = $data['cost_per_unit'] / $data['subdivisions'];
        }

        $packaging = $tenant->packagings()->create($data);

        $packaging->priceLogs()->create([
            'cost_per_unit' => $packaging->cost_per_unit,
            'recorded_at' => now(),
        ]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'packaging',
            targetId: $packaging->id,
            action: 'packaging.created',
            payload: ['name' => $packaging->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('packaging.index'))->with('status', 'Envase creado.');
    }

    public function update(UpdatePackagingRequest $request, Packaging $packaging): RedirectResponse
    {
        $this->authorize('update', $packaging);

        $data = $request->validated();

        if (! empty($data['subdivisions'])) {
            $data['cost_per_package'] = $data['cost_per_unit'];
            $data['cost_per_unit'] = $data['cost_per_unit'] / $data['subdivisions'];
        } else {
            $data['cost_per_package'] = null;
        }

        $costChanged = (float) $packaging->cost_per_unit !== (float) $data['cost_per_unit'];

        if ($costChanged) {
            $packaging->priceLogs()->create([
                'cost_per_unit' => $data['cost_per_unit'],
                'recorded_at' => now(),
            ]);
        }

        $packaging->update($data);

        if ($costChanged) {
            $this->propagator->propagateFromPackaging($packaging->id);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'packaging',
            targetId: $packaging->id,
            action: 'packaging.updated',
            payload: ['name' => $packaging->name],
            tenantId: $packaging->tenant_id,
        );

        return back(fallback: route('packaging.index'))->with('status', 'Envase actualizado.');
    }

    public function toggleActive(Packaging $packaging): RedirectResponse
    {
        $this->authorize('update', $packaging);

        // Mismo guard que IngredientController::toggleActive(): sin él, un
        // descartable descontinuado podía desactivarse mientras recetas
        // activas seguían costeando con él en silencio.
        if ($packaging->active) {
            $blockedBy = DB::table('recipe_packaging_lines')
                ->join('recipes', 'recipes.id', '=', 'recipe_packaging_lines.recipe_id')
                ->where('recipe_packaging_lines.packaging_id', $packaging->id)
                ->where('recipes.active', true)
                ->distinct()
                ->pluck('recipes.name')
                ->toArray();

            if (! empty($blockedBy)) {
                return back()->withErrors([
                    'toggle' => 'No podés desactivar este descartable porque lo usan: '
                        .implode(', ', $blockedBy).'. Reemplazalo en esas recetas primero.',
                ]);
            }
        }

        $packaging->update(['active' => ! $packaging->active]);
        $action = $packaging->active ? 'packaging.activated' : 'packaging.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'packaging',
            targetId: $packaging->id,
            action: $action,
            payload: ['name' => $packaging->name],
            tenantId: $packaging->tenant_id,
        );

        $label = $packaging->active ? 'activado' : 'desactivado';

        return back()->with('status', "Envase {$label}.");
    }
}

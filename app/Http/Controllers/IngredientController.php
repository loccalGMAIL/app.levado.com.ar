<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\Ingredient;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostPropagator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly RecipeCostPropagator $propagator,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $sortable = ['name', 'cost_per_unit'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $ingredients = $tenant->ingredients()
            ->with('supplier')
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();
        $suppliers = $tenant->suppliers()->active()->orderBy('name')->get();

        return view('ingredients.index', compact('ingredients', 'suppliers'));
    }

    public function store(StoreIngredientRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();

        if (! empty($data['subdivisions']) && ($data['unit'] ?? '') === 'u') {
            $data['cost_per_package'] = $data['cost_per_unit'];
            $data['cost_per_unit'] = $data['cost_per_unit'] / $data['subdivisions'];
        }

        $ingredient = $tenant->ingredients()->create($data);

        $ingredient->priceLogs()->create([
            'cost_per_unit' => $ingredient->cost_per_unit,
            'recorded_at' => now(),
        ]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'ingredient',
            targetId: $ingredient->id,
            action: 'ingredient.created',
            payload: ['name' => $ingredient->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('ingredients.index'))->with('status', 'Ingrediente creado.');
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('update', $ingredient);

        $data = $request->validated();

        if (! empty($data['subdivisions']) && ($data['unit'] ?? $ingredient->unit->value) === 'u') {
            $data['cost_per_package'] = $data['cost_per_unit'];
            $data['cost_per_unit'] = $data['cost_per_unit'] / $data['subdivisions'];
        } else {
            $data['cost_per_package'] = null;
        }

        $costChanged = (float) $ingredient->cost_per_unit !== (float) $data['cost_per_unit'];

        if ($costChanged) {
            $ingredient->priceLogs()->create([
                'cost_per_unit' => $data['cost_per_unit'],
                'recorded_at' => now(),
            ]);
        }

        $ingredient->update($data);

        if ($costChanged) {
            $this->propagator->propagateFromIngredient($ingredient->id);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'ingredient',
            targetId: $ingredient->id,
            action: 'ingredient.updated',
            payload: ['name' => $ingredient->name],
            tenantId: $ingredient->tenant_id,
        );

        return back(fallback: route('ingredients.index'))->with('status', 'Ingrediente actualizado.');
    }

    public function toggleActive(Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('update', $ingredient);

        $ingredient->update(['active' => ! $ingredient->active]);
        $action = $ingredient->active ? 'ingredient.activated' : 'ingredient.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'ingredient',
            targetId: $ingredient->id,
            action: $action,
            payload: ['name' => $ingredient->name],
            tenantId: $ingredient->tenant_id,
        );

        $label = $ingredient->active ? 'activado' : 'desactivado';

        return back()->with('status', "Ingrediente {$label}.");
    }
}

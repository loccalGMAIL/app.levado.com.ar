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
            ->when(request('status') === 'active', fn ($q) => $q->where('active', true))
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();
        $suppliers = $tenant->suppliers()->where('active', true)->orderBy('name')->get();

        return view('ingredients.index', compact('ingredients', 'suppliers'));
    }

    public function store(StoreIngredientRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $ingredient = $tenant->ingredients()->create($request->validated());

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

        return redirect()->route('ingredients.index')->with('status', 'Ingrediente creado.');
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $this->authorizeIngredient($ingredient);

        $data = $request->validated();

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

        return redirect()->route('ingredients.index')->with('status', 'Ingrediente actualizado.');
    }

    public function toggleActive(Ingredient $ingredient): RedirectResponse
    {
        $this->authorizeIngredient($ingredient);

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

    private function authorizeIngredient(Ingredient $ingredient): void
    {
        abort_unless($ingredient->tenant_id === app(Tenant::class)->id, 403);
    }
}

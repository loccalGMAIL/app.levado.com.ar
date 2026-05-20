<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\Ingredient;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $ingredients = $tenant->ingredients()
            ->with('supplier')
            ->orderBy('name')
            ->get();
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

        if ((float) $ingredient->cost_per_unit !== (float) $data['cost_per_unit']) {
            $ingredient->priceLogs()->create([
                'cost_per_unit' => $data['cost_per_unit'],
                'recorded_at' => now(),
            ]);
        }

        $ingredient->update($data);

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

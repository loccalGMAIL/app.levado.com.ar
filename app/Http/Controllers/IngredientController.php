<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\Ingredient;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class IngredientController extends Controller
{
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
        $ingredient = app(Tenant::class)->ingredients()->create($request->validated());

        $ingredient->priceLogs()->create([
            'cost_per_unit' => $ingredient->cost_per_unit,
            'recorded_at' => now(),
        ]);

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

        return redirect()->route('ingredients.index')->with('status', 'Ingrediente actualizado.');
    }

    public function toggleActive(Ingredient $ingredient): RedirectResponse
    {
        $this->authorizeIngredient($ingredient);

        $ingredient->update(['active' => ! $ingredient->active]);

        $label = $ingredient->active ? 'activado' : 'desactivado';

        return back()->with('status', "Ingrediente {$label}.");
    }

    private function authorizeIngredient(Ingredient $ingredient): void
    {
        abort_unless($ingredient->tenant_id === app(Tenant::class)->id, 403);
    }
}

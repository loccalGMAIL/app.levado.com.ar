<?php

namespace App\Http\Controllers;

use App\Enums\Unit;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeLaborLine;
use App\Models\RecipePackagingLine;
use App\Models\Tenant;
use App\Services\RecipeCostCalculator;
use App\Services\UnitConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function index(): View
    {
        $recipes = app(Tenant::class)->recipes()
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        return view('recipes.index', compact('recipes'));
    }

    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $recipe = app(Tenant::class)->recipes()->create($request->validated());

        return redirect()->route('recipes.show', $recipe)->with('status', 'Receta creada.');
    }

    public function show(Recipe $recipe): View
    {
        $this->authorizeRecipe($recipe);

        $costs = (new RecipeCostCalculator(new UnitConverter))->calculate($recipe);

        $tenant = app(Tenant::class);
        $ingredients = $tenant->ingredients()->where('active', true)->orderBy('name')->get();
        $packagings = $tenant->packagings()->where('active', true)->orderBy('name')->get();
        $laborTypes = $tenant->laborTypes()->where('active', true)->orderBy('name')->get();

        return view('recipes.show', [
            'recipe' => $recipe,
            'ingredientCost' => $costs['ingredient_cost'],
            'packagingCost' => $costs['packaging_cost'],
            'laborCost' => $costs['labor_cost'],
            'totalCost' => $costs['total_cost'],
            'costPerUnit' => $costs['cost_per_unit'],
            'ingredients' => $ingredients,
            'packagings' => $packagings,
            'laborTypes' => $laborTypes,
        ]);
    }

    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        $recipe->update($request->validated());

        return redirect()->route('recipes.show', $recipe)->with('status', 'Receta actualizada.');
    }

    public function toggleActive(Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        $recipe->update(['active' => ! $recipe->active]);
        $label = $recipe->active ? 'activada' : 'desactivada';

        return back()->with('status', "Receta {$label}.");
    }

    public function storeIngredientLine(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);

        $data = $request->validate([
            'ingredient_id' => [
                'required', 'integer',
                Rule::exists('ingredients', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', Rule::enum(Unit::class)],
        ]);

        $ingredient = Ingredient::find($data['ingredient_id']);
        $converter = new UnitConverter;
        abort_unless(
            $converter->compatible(Unit::from($data['unit']), $ingredient->unit),
            422,
            'La unidad seleccionada no es compatible con la unidad del ingrediente.'
        );

        $recipe->ingredientLines()->create($data);

        return redirect()->route('recipes.show', $recipe)->with('status', 'Ingrediente agregado.');
    }

    public function destroyIngredientLine(Recipe $recipe, RecipeIngredientLine $line): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);
        $line->delete();

        return redirect()->route('recipes.show', $recipe)->with('status', 'Ingrediente eliminado.');
    }

    public function storePackagingLine(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);

        $data = $request->validate([
            'packaging_id' => [
                'required', 'integer',
                Rule::exists('packagings', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $recipe->packagingLines()->create($data);

        return redirect()->route('recipes.show', $recipe)->with('status', 'Envase agregado.');
    }

    public function destroyPackagingLine(Recipe $recipe, RecipePackagingLine $line): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);
        $line->delete();

        return redirect()->route('recipes.show', $recipe)->with('status', 'Envase eliminado.');
    }

    public function storeLaborLine(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);

        $data = $request->validate([
            'labor_type_id' => [
                'required', 'integer',
                Rule::exists('labor_types', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'hours' => ['required', 'numeric', 'min:0.01'],
        ]);

        $recipe->laborLines()->create($data);

        return redirect()->route('recipes.show', $recipe)->with('status', 'Mano de obra agregada.');
    }

    public function destroyLaborLine(Recipe $recipe, RecipeLaborLine $line): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);
        $line->delete();

        return redirect()->route('recipes.show', $recipe)->with('status', 'Mano de obra eliminada.');
    }

    private function authorizeRecipe(Recipe $recipe): void
    {
        abort_unless($recipe->tenant_id === app(Tenant::class)->id, 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeLaborLine;
use App\Models\RecipePackagingLine;
use App\Models\RecipeSubrecipeLine;
use App\Models\Tenant;
use App\Services\RecipeCostPropagator;
use App\Services\UnitConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CRUD de las líneas de una receta (ingredientes, envases, mano de obra y
 * sub-recetas). Todas las mutaciones terminan en propagateFrom(): el costo
 * de la receta y de sus ancestros se recalcula en la misma request.
 * Los bindings {ingredientLine}/{packagingLine}/{laborLine}/{subrecipeLine}
 * usan scoped bindings: una línea de otra receta resuelve 404.
 */
class RecipeLineController extends Controller
{
    public function __construct(
        private readonly RecipeCostPropagator $propagator,
        private readonly UnitConverter $converter,
    ) {}

    public function storeIngredientLine(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $data = $request->validate([
            'ingredient_id' => [
                'required', 'integer',
                Rule::exists('ingredients', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', Rule::enum(Unit::class)],
        ]);

        $ingredient = Ingredient::find($data['ingredient_id']);

        if (! $this->converter->compatible(Unit::from($data['unit']), $ingredient->unit)) {
            throw ValidationException::withMessages([
                'unit' => 'La unidad seleccionada no es compatible con la unidad del ingrediente.',
            ]);
        }

        $recipe->ingredientLines()->create($data);
        $this->propagator->propagateFrom($recipe);

        return back(fallback: route('recipes.show', $recipe))->with('status', 'Ingrediente agregado.');
    }

    public function updateIngredientLine(Request $request, Recipe $recipe, RecipeIngredientLine $ingredientLine): JsonResponse
    {
        $this->authorize('update', $recipe);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', Rule::enum(Unit::class)],
        ]);

        $ingredient = $ingredientLine->ingredient;

        if (! $this->converter->compatible(Unit::from($data['unit']), $ingredient->unit)) {
            throw ValidationException::withMessages([
                'unit' => 'La unidad seleccionada no es compatible con la unidad del ingrediente.',
            ]);
        }

        $ingredientLine->update($data);
        $this->propagator->propagateFrom($recipe);

        $subLabel = $ingredient->subdivisions ? ($ingredient->subdivision_label ?? 'u') : null;

        return response()->json([
            'ok' => true,
            'unitLabel' => $subLabel ?? $ingredientLine->unit->short(),
            'costPerLineUnit' => $this->converter->convert(1.0, $ingredientLine->unit, $ingredient->unit) * (float) $ingredient->cost_per_unit,
        ]);
    }

    public function destroyIngredientLine(Recipe $recipe, RecipeIngredientLine $ingredientLine): RedirectResponse
    {
        $this->authorize('update', $recipe);
        $ingredientLine->delete();
        $this->propagator->propagateFrom($recipe);

        return back(fallback: route('recipes.show', $recipe))->with('status', 'Ingrediente eliminado.');
    }

    public function storePackagingLine(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $data = $request->validate([
            'packaging_id' => [
                'required', 'integer',
                Rule::exists('packagings', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $recipe->packagingLines()->create($data);
        $this->propagator->propagateFrom($recipe);

        return back(fallback: route('recipes.show', $recipe))->with('status', 'Envase agregado.');
    }

    public function updatePackagingLine(Request $request, Recipe $recipe, RecipePackagingLine $packagingLine): JsonResponse
    {
        $this->authorize('update', $recipe);

        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0.001']]);
        $packagingLine->update($data);
        $this->propagator->propagateFrom($recipe);

        return response()->json(['ok' => true]);
    }

    public function destroyPackagingLine(Recipe $recipe, RecipePackagingLine $packagingLine): RedirectResponse
    {
        $this->authorize('update', $recipe);
        $packagingLine->delete();
        $this->propagator->propagateFrom($recipe);

        return back(fallback: route('recipes.show', $recipe))->with('status', 'Envase eliminado.');
    }

    public function storeLaborLine(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $data = $request->validate([
            'labor_type_id' => [
                'required', 'integer',
                Rule::exists('labor_types', 'id')->where('tenant_id', app(Tenant::class)->id),
            ],
            'hours' => ['required', 'numeric', 'min:0.01'],
        ]);

        $recipe->laborLines()->create($data);
        $this->propagator->propagateFrom($recipe);

        return back(fallback: route('recipes.show', $recipe))->with('status', 'Mano de obra agregada.');
    }

    public function updateLaborLine(Request $request, Recipe $recipe, RecipeLaborLine $laborLine): JsonResponse
    {
        $this->authorize('update', $recipe);

        $data = $request->validate(['hours' => ['required', 'numeric', 'min:0.01']]);
        $laborLine->update($data);
        $this->propagator->propagateFrom($recipe);

        return response()->json(['ok' => true]);
    }

    public function destroyLaborLine(Recipe $recipe, RecipeLaborLine $laborLine): RedirectResponse
    {
        $this->authorize('update', $recipe);
        $laborLine->delete();
        $this->propagator->propagateFrom($recipe);

        return back(fallback: route('recipes.show', $recipe))->with('status', 'Mano de obra eliminada.');
    }

    public function storeSubrecipeLine(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);
        $tenant = app(Tenant::class);

        $data = $request->validate([
            'child_recipe_id' => [
                'required', 'integer',
                Rule::exists('recipes', 'id')
                    ->where('tenant_id', $tenant->id)
                    ->where('is_semi_elaborate', true)
                    ->where('active', true),
            ],
            'quantity_used' => ['required', 'numeric', 'min:0.001'],
            'unit' => ['required', Rule::enum(Unit::class)],
        ]);

        $child = Recipe::find($data['child_recipe_id']);

        if (! $this->converter->compatible(Unit::from($data['unit']), $child->yield_unit)) {
            throw ValidationException::withMessages([
                'unit' => 'La unidad debe ser compatible con la unidad de rendimiento de la sub-receta ('.$child->yield_unit->short().').',
            ]);
        }

        if ($this->propagator->isAncestor((int) $data['child_recipe_id'], $recipe->id, $tenant->id)) {
            throw ValidationException::withMessages([
                'child_recipe_id' => 'Esta sub-receta crearía un ciclo en el árbol de recetas.',
            ]);
        }

        $recipe->subrecipeLines()->create($data);
        $this->propagator->propagateFrom($recipe);

        return back(fallback: route('recipes.show', $recipe))->with('status', 'Sub-receta agregada.');
    }

    public function updateSubrecipeLine(Request $request, Recipe $recipe, RecipeSubrecipeLine $subrecipeLine): JsonResponse
    {
        $this->authorize('update', $recipe);

        $data = $request->validate(['quantity_used' => ['required', 'numeric', 'min:0.001']]);
        $subrecipeLine->update($data);
        $this->propagator->propagateFrom($recipe);

        return response()->json(['ok' => true]);
    }

    public function destroySubrecipeLine(Recipe $recipe, RecipeSubrecipeLine $subrecipeLine): RedirectResponse
    {
        $this->authorize('update', $recipe);
        $subrecipeLine->delete();
        $this->propagator->propagateFrom($recipe);

        return back(fallback: route('recipes.show', $recipe))->with('status', 'Sub-receta eliminada.');
    }
}

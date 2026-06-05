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
use App\Models\RecipeSubrecipeLine;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostCalculator;
use App\Services\RecipeCostPropagator;
use App\Services\UnitConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly RecipeCostPropagator $propagator,
    ) {}

    public function index(): View
    {
        $sortable = ['name', 'yield_quantity', 'selling_price'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $recipes = app(Tenant::class)->recipes()
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->where('active', true))
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        return view('recipes.index', compact('recipes'));
    }

    public function copy(Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);

        $recipe->load(['ingredientLines', 'laborLines', 'packagingLines', 'subrecipeLines']);

        $newRecipe = $recipe->replicate(['unit_cost']);
        $newRecipe->name = $recipe->name.' (copia)';
        $newRecipe->active = false;
        $newRecipe->unit_cost = null;
        $newRecipe->save();

        foreach ($recipe->ingredientLines as $line) {
            $newRecipe->ingredientLines()->create([
                'ingredient_id' => $line->ingredient_id,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
            ]);
        }
        foreach ($recipe->laborLines as $line) {
            $newRecipe->laborLines()->create([
                'labor_type_id' => $line->labor_type_id,
                'hours' => $line->hours,
            ]);
        }
        foreach ($recipe->packagingLines as $line) {
            $newRecipe->packagingLines()->create([
                'packaging_id' => $line->packaging_id,
                'quantity' => $line->quantity,
            ]);
        }
        foreach ($recipe->subrecipeLines as $line) {
            $newRecipe->subrecipeLines()->create([
                'child_recipe_id' => $line->child_recipe_id,
                'quantity_used' => $line->quantity_used,
                'unit' => $line->unit,
            ]);
        }

        $this->propagator->propagateFrom($newRecipe);

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'recipe',
            targetId: $newRecipe->id,
            action: 'recipe.copied',
            payload: ['name' => $newRecipe->name, 'source_id' => $recipe->id],
            tenantId: $newRecipe->tenant_id,
        );

        return redirect()->route('recipes.show', $newRecipe)
            ->with('status', 'Receta copiada. Revisá el nombre y activala cuando esté lista.');
    }

    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $recipe = $tenant->recipes()->create($request->validated());

        if ($tenant->onboarding_completed_at === null) {
            $tenant->update(['onboarding_completed_at' => now()]);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'recipe',
            targetId: $recipe->id,
            action: 'recipe.created',
            payload: ['name' => $recipe->name],
            tenantId: $tenant->id,
        );

        return redirect()->route('recipes.show', $recipe)->with('status', 'Receta creada.');
    }

    public function show(Recipe $recipe): View
    {
        $this->authorizeRecipe($recipe);

        $recipe->loadMissing([
            'ingredientLines.ingredient.supplier',
            'packagingLines.packaging',
            'laborLines.laborType',
            'subrecipeLines.childRecipe',
        ]);

        $costs = (new RecipeCostCalculator(new UnitConverter))->calculate($recipe);

        $tenant = app(Tenant::class);
        $ingredients = $tenant->ingredients()->where('active', true)->orderBy('name')->get();
        $packagings = $tenant->packagings()->where('active', true)->orderBy('name')->get();
        $laborTypes = $tenant->laborTypes()->where('active', true)->orderBy('name')->get();

        $totalFixedCosts = $tenant->fixedCosts()->where('active', true)->sum('monthly_amount');
        $productiveHours = (int) $tenant->productive_hours_month;
        $overheadPerHour = $productiveHours > 0 ? (float) $totalFixedCosts / $productiveHours : 0.0;

        $converter = new UnitConverter;

        $ingredientLinesData = $recipe->ingredientLines->map(fn ($line) => [
            'id' => $line->id,
            'name' => $line->ingredient->name,
            'code' => 'ING-'.str_pad($line->ingredient->id, 3, '0', STR_PAD_LEFT),
            'supplier' => $line->ingredient->supplier?->name,
            'quantity' => (float) $line->quantity,
            'unit' => $line->unit->value,
            'unitLabel' => $line->unit->short(),
            'costPerLineUnit' => $converter->convert(1.0, $line->unit, $line->ingredient->unit) * (float) $line->ingredient->cost_per_unit,
            'refCost' => (float) $line->ingredient->cost_per_unit,
            'refUnit' => $line->ingredient->unit->short(),
        ]);

        $laborLinesData = $recipe->laborLines->map(fn ($line) => [
            'id' => $line->id,
            'name' => $line->laborType->name,
            'hours' => (float) $line->hours,
            'hourlyRate' => (float) $line->laborType->hourly_rate,
        ]);

        $packagingLinesData = $recipe->packagingLines->map(fn ($line) => [
            'id' => $line->id,
            'name' => $line->packaging->name,
            'quantity' => (float) $line->quantity,
            'costPerUnit' => (float) $line->packaging->cost_per_unit,
        ]);

        $subrecipeLinesData = $recipe->subrecipeLines->map(function ($line) use ($converter) {
            $child = $line->childRecipe;
            $childUnitCost = (float) ($child->unit_cost ?? 0);
            $factor = $converter->convert(1.0, $line->unit, $child->yield_unit);
            $costPerLineUnit = ($factor !== null) ? $factor * $childUnitCost : 0.0;

            return [
                'id' => $line->id,
                'name' => $child->name,
                'quantity' => (float) $line->quantity_used,
                'unit' => $line->unit->value,
                'unitLabel' => $line->unit->short(),
                'unitCost' => $childUnitCost,
                'costPerLineUnit' => $costPerLineUnit,
                'childYieldUnit' => $child->yield_unit->short(),
            ];
        });

        $semiElaborates = $this->availableSemiElaborates($recipe, $tenant);

        return view('recipes.show', [
            'recipe' => $recipe,
            'ingredientCost' => $costs['ingredient_cost'],
            'packagingCost' => $costs['packaging_cost'],
            'laborCost' => $costs['labor_cost'],
            'subrecipeCost' => $costs['subrecipe_cost'],
            'totalCost' => $costs['total_cost'],
            'costPerUnit' => $costs['cost_per_unit'],
            'overheadPerHour' => $overheadPerHour,
            'ingredients' => $ingredients,
            'packagings' => $packagings,
            'laborTypes' => $laborTypes,
            'ingredientLinesData' => $ingredientLinesData,
            'laborLinesData' => $laborLinesData,
            'packagingLinesData' => $packagingLinesData,
            'subrecipeLinesData' => $subrecipeLinesData,
            'semiElaborates' => $semiElaborates,
        ]);
    }

    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        $recipe->update($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'recipe',
            targetId: $recipe->id,
            action: 'recipe.updated',
            payload: ['name' => $recipe->name],
            tenantId: $recipe->tenant_id,
        );

        return redirect()->route('recipes.show', $recipe)->with('status', 'Receta actualizada.');
    }

    public function updateSellingPrice(Request $request, Recipe $recipe): JsonResponse
    {
        $this->authorizeRecipe($recipe);

        $validated = $request->validate([
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $recipe->update(['selling_price' => $validated['selling_price']]);

        $calculator = new RecipeCostCalculator(new UnitConverter);
        $recipe->load(['ingredientLines.ingredient', 'packagingLines.packaging', 'laborLines.laborType']);
        $costs = $calculator->calculate($recipe);

        $sellingPrice = $recipe->selling_price !== null ? (float) $recipe->selling_price : null;
        $costPerUnit = $costs['cost_per_unit'];

        $margin = null;
        $marginPct = null;
        $marginColor = 'text-masa-madre';

        if ($sellingPrice !== null && $costPerUnit !== null && $sellingPrice > 0) {
            $margin = $sellingPrice - $costPerUnit;
            $marginPct = ($margin / $sellingPrice) * 100;
            $marginColor = $marginPct >= 30 ? 'text-green-600' : ($marginPct >= 15 ? 'text-amber-600' : 'text-red-500');
        }

        return response()->json([
            'selling_price' => $sellingPrice,
            'selling_price_formatted' => $sellingPrice !== null ? number_format($sellingPrice, 2, ',', '.') : null,
            'margin' => $margin,
            'margin_formatted' => $margin !== null ? number_format($margin, 2, ',', '.') : null,
            'margin_pct' => $marginPct,
            'margin_pct_formatted' => $marginPct !== null ? number_format($marginPct, 1, ',', '.') : null,
            'margin_color' => $marginColor,
        ]);
    }

    public function toggleActive(Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);

        if ($recipe->active && $recipe->is_semi_elaborate) {
            $blockedBy = DB::table('recipe_subrecipe_lines')
                ->join('recipes', 'recipes.id', '=', 'recipe_subrecipe_lines.recipe_id')
                ->where('recipe_subrecipe_lines.child_recipe_id', $recipe->id)
                ->where('recipes.active', true)
                ->pluck('recipes.name')
                ->toArray();

            if (! empty($blockedBy)) {
                return back()->withErrors([
                    'toggle' => 'No podés desactivar esta sub-receta porque está siendo usada por: '
                        .implode(', ', $blockedBy).'.',
                ]);
            }
        }

        $recipe->update(['active' => ! $recipe->active]);
        $action = $recipe->active ? 'recipe.activated' : 'recipe.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'recipe',
            targetId: $recipe->id,
            action: $action,
            payload: ['name' => $recipe->name],
            tenantId: $recipe->tenant_id,
        );

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

        if (! $converter->compatible(Unit::from($data['unit']), $ingredient->unit)) {
            throw ValidationException::withMessages([
                'unit' => 'La unidad seleccionada no es compatible con la unidad del ingrediente.',
            ]);
        }

        $recipe->ingredientLines()->create($data);
        $this->propagator->propagateFrom($recipe);

        return redirect()->route('recipes.show', $recipe)->with('status', 'Ingrediente agregado.');
    }

    public function destroyIngredientLine(Recipe $recipe, RecipeIngredientLine $line): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);
        $line->delete();
        $this->propagator->propagateFrom($recipe);

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
        $this->propagator->propagateFrom($recipe);

        return redirect()->route('recipes.show', $recipe)->with('status', 'Envase agregado.');
    }

    public function destroyPackagingLine(Recipe $recipe, RecipePackagingLine $line): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);
        $line->delete();
        $this->propagator->propagateFrom($recipe);

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
        $this->propagator->propagateFrom($recipe);

        return redirect()->route('recipes.show', $recipe)->with('status', 'Mano de obra agregada.');
    }

    public function destroyLaborLine(Recipe $recipe, RecipeLaborLine $line): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);
        $line->delete();
        $this->propagator->propagateFrom($recipe);

        return redirect()->route('recipes.show', $recipe)->with('status', 'Mano de obra eliminada.');
    }

    public function updateIngredientLine(Request $request, Recipe $recipe, RecipeIngredientLine $line): JsonResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);

        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0.001']]);
        $line->update($data);
        $this->propagator->propagateFrom($recipe);

        return response()->json(['ok' => true]);
    }

    public function updatePackagingLine(Request $request, Recipe $recipe, RecipePackagingLine $line): JsonResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);

        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0.001']]);
        $line->update($data);
        $this->propagator->propagateFrom($recipe);

        return response()->json(['ok' => true]);
    }

    public function updateLaborLine(Request $request, Recipe $recipe, RecipeLaborLine $line): JsonResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);

        $data = $request->validate(['hours' => ['required', 'numeric', 'min:0.01']]);
        $line->update($data);
        $this->propagator->propagateFrom($recipe);

        return response()->json(['ok' => true]);
    }

    public function storeSubrecipeLine(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
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
        $converter = new UnitConverter;

        if (! $converter->compatible(Unit::from($data['unit']), $child->yield_unit)) {
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

        return redirect()->route('recipes.show', $recipe)->with('status', 'Sub-receta agregada.');
    }

    public function updateSubrecipeLine(Request $request, Recipe $recipe, RecipeSubrecipeLine $line): JsonResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);

        $data = $request->validate(['quantity_used' => ['required', 'numeric', 'min:0.001']]);
        $line->update($data);
        $this->propagator->propagateFrom($recipe);

        return response()->json(['ok' => true]);
    }

    public function destroySubrecipeLine(Recipe $recipe, RecipeSubrecipeLine $line): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);
        $line->delete();
        $this->propagator->propagateFrom($recipe);

        return redirect()->route('recipes.show', $recipe)->with('status', 'Sub-receta eliminada.');
    }

    private function availableSemiElaborates(Recipe $recipe, Tenant $tenant): Collection
    {
        return $tenant->recipes()
            ->where('is_semi_elaborate', true)
            ->where('active', true)
            ->where('id', '!=', $recipe->id)
            ->get()
            ->filter(fn ($candidate) => ! $this->propagator->isAncestor($candidate->id, $recipe->id, $tenant->id))
            ->values();
    }

    private function authorizeRecipe(Recipe $recipe): void
    {
        abort_unless($recipe->tenant_id === app(Tenant::class)->id, 403);
    }
}

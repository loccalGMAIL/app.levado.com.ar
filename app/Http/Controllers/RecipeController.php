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
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostCalculator;
use App\Services\UnitConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

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

        return view('recipes.show', [
            'recipe' => $recipe,
            'ingredientCost' => $costs['ingredient_cost'],
            'packagingCost' => $costs['packaging_cost'],
            'laborCost' => $costs['labor_cost'],
            'totalCost' => $costs['total_cost'],
            'costPerUnit' => $costs['cost_per_unit'],
            'overheadPerHour' => $overheadPerHour,
            'ingredients' => $ingredients,
            'packagings' => $packagings,
            'laborTypes' => $laborTypes,
            'ingredientLinesData' => $ingredientLinesData,
            'laborLinesData' => $laborLinesData,
            'packagingLinesData' => $packagingLinesData,
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

    public function toggleActive(Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($recipe);
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

    public function updateIngredientLine(Request $request, Recipe $recipe, RecipeIngredientLine $line): JsonResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);

        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0.001']]);
        $line->update($data);

        return response()->json(['ok' => true]);
    }

    public function updatePackagingLine(Request $request, Recipe $recipe, RecipePackagingLine $line): JsonResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);

        $data = $request->validate(['quantity' => ['required', 'numeric', 'min:0.001']]);
        $line->update($data);

        return response()->json(['ok' => true]);
    }

    public function updateLaborLine(Request $request, Recipe $recipe, RecipeLaborLine $line): JsonResponse
    {
        $this->authorizeRecipe($recipe);
        abort_unless($line->recipe_id === $recipe->id, 403);

        $data = $request->validate(['hours' => ['required', 'numeric', 'min:0.01']]);
        $line->update($data);

        return response()->json(['ok' => true]);
    }

    private function authorizeRecipe(Recipe $recipe): void
    {
        abort_unless($recipe->tenant_id === app(Tenant::class)->id, 403);
    }
}

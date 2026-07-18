<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Recipe;
use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostPropagator;
use App\Services\RecipePriceWriter;
use App\Services\RecipeShowViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRUD de la receta en sí. Las líneas (ingredientes, envases, mano de obra,
 * sub-recetas) viven en RecipeLineController; los datos de presentación de
 * /recipes/{id} los arma RecipeShowViewModel.
 */
class RecipeController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly RecipeCostPropagator $propagator,
        private readonly RecipePriceWriter $priceWriter,
        private readonly RecipeShowViewModel $showViewModel,
    ) {}

    public function index(): View
    {
        $sortable = ['name', 'yield_quantity', 'selling_price'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $tenant = app(Tenant::class);
        $priceLists = $tenant->priceLists()->active()->orderByDesc('is_default')->orderBy('name')->get();
        $priceList = $priceLists->firstWhere('id', (int) request('price_list'))
            ?? $priceLists->firstWhere('is_default', true)
            ?? $priceLists->first()
            ?? $tenant->defaultPriceList();

        $recipes = $tenant->recipes()
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort === 'selling_price', fn ($q) => $q->orderBy(
                RecipePrice::select('price')
                    ->whereColumn('recipe_id', 'recipes.id')
                    ->where('price_list_id', $priceList->id),
                $dir,
            ))
            ->when($sort && $sort !== 'selling_price', fn ($q) => $q->orderBy($sort, $dir))
            ->when(! $sort, fn ($q) => $q->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        $prices = RecipePrice::where('price_list_id', $priceList->id)
            ->whereIn('recipe_id', $recipes->pluck('id'))
            ->pluck('price', 'recipe_id');

        return view('recipes.index', compact('recipes', 'priceList', 'priceLists', 'prices'));
    }

    public function show(Recipe $recipe): View
    {
        $this->authorize('update', $recipe);

        return view('recipes.show', $this->showViewModel->build($recipe, app(Tenant::class)));
    }

    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);

        $data = $request->validated();
        $sellingPrice = $data['selling_price'] ?? null;
        unset($data['selling_price']);

        $recipe = $tenant->recipes()->create($data);

        if ($sellingPrice !== null) {
            $this->priceWriter->set($recipe, $tenant->defaultPriceList(), (float) $sellingPrice);
        }

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

    public function update(UpdateRecipeRequest $request, Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $data = $request->validated();
        $sellingPrice = $data['selling_price'] ?? null;
        $sellingPriceSent = array_key_exists('selling_price', $data);
        unset($data['selling_price']);

        $recipe->update($data);

        if ($sellingPriceSent) {
            $this->priceWriter->set(
                $recipe,
                app(Tenant::class)->defaultPriceList(),
                $sellingPrice !== null ? (float) $sellingPrice : null,
            );
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'recipe',
            targetId: $recipe->id,
            action: 'recipe.updated',
            payload: ['name' => $recipe->name],
            tenantId: $recipe->tenant_id,
        );

        return back(fallback: route('recipes.show', $recipe))->with('status', 'Receta actualizada.');
    }

    public function copy(Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

        $recipe->load(['ingredientLines', 'laborLines', 'packagingLines', 'subrecipeLines']);

        $newRecipe = DB::transaction(function () use ($recipe) {
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
            foreach ($recipe->prices()->with('priceList')->get() as $recipePrice) {
                $this->priceWriter->set($newRecipe, $recipePrice->priceList, (float) $recipePrice->price);
            }

            $this->propagator->propagateFrom($newRecipe);

            return $newRecipe;
        });

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

    public function toggleActive(Recipe $recipe): RedirectResponse
    {
        $this->authorize('update', $recipe);

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
}

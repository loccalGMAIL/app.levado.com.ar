<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceListRequest;
use App\Http\Requests\UpdatePriceListRequest;
use App\Models\PriceList;
use App\Models\Recipe;
use App\Models\RecipePrice;
use App\Models\RecipePriceLog;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PriceListController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
    ) {}

    public function index(): View
    {
        $sortable = ['name', 'adjustment_pct'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : null;
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $tenant = app(Tenant::class);
        $tenant->defaultPriceList();

        $priceLists = $tenant->priceLists()
            ->withCount('prices')
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('is_default')->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        return view('price-lists.index', compact('priceLists'));
    }

    public function matrix(): View
    {
        $tenant = app(Tenant::class);
        // defaultPriceList() debe correr ANTES del get(): $priceLists se renderiza
        // completa (una columna por lista) y tiene que incluir la recién creada.
        $tenant->defaultPriceList();

        $priceLists = $tenant->priceLists()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $defaultList = $priceLists->firstWhere('is_default', true);

        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $recipes = $tenant->recipes()
            ->active()
            ->where('is_semi_elaborate', false)
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->orderBy('name', $dir)
            ->paginate(20)
            ->withQueryString();

        // unit_cost cacheado (mantenido por RecipeCostPropagator) — mismo valor
        // que devolvía el calculator, sin recalcular ni cargar líneas.
        $costsPerUnit = collect($recipes->items())
            ->mapWithKeys(fn ($recipe) => [
                $recipe->id => $recipe->unit_cost !== null ? (float) $recipe->unit_cost : null,
            ]);

        /** @var array<int, array<int, string>> $prices [recipe_id][price_list_id] => price */
        $prices = RecipePrice::whereIn('price_list_id', $priceLists->pluck('id'))
            ->whereIn('recipe_id', collect($recipes->items())->pluck('id'))
            ->get()
            ->groupBy('recipe_id')
            ->map(fn ($group) => $group->pluck('price', 'price_list_id'));

        return view('price-lists.matrix', compact('priceLists', 'defaultList', 'recipes', 'costsPerUnit', 'prices'));
    }

    public function store(StorePriceListRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $priceList = $tenant->priceLists()->create($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'price_list',
            targetId: $priceList->id,
            action: 'price_list.created',
            payload: ['name' => $priceList->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('price-lists.index'))->with('status', 'Lista de precios creada.');
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList): RedirectResponse
    {
        $this->authorize('update', $priceList);

        $data = $request->validated();

        if ($priceList->is_default) {
            $data['adjustment_pct'] = null;
        }

        $priceList->update($data);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'price_list',
            targetId: $priceList->id,
            action: 'price_list.updated',
            payload: ['name' => $priceList->name],
            tenantId: $priceList->tenant_id,
        );

        return back(fallback: route('price-lists.index'))->with('status', 'Lista de precios actualizada.');
    }

    public function toggleActive(PriceList $priceList): RedirectResponse
    {
        $this->authorize('update', $priceList);

        if ($priceList->is_default) {
            return back()->withErrors(['toggle' => 'No podés desactivar la lista base.']);
        }

        $priceList->update(['active' => ! $priceList->active]);
        $action = $priceList->active ? 'price_list.activated' : 'price_list.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'price_list',
            targetId: $priceList->id,
            action: $action,
            payload: ['name' => $priceList->name],
            tenantId: $priceList->tenant_id,
        );

        $label = $priceList->active ? 'activada' : 'desactivada';

        return back()->with('status', "Lista de precios {$label}.");
    }

    public function applySuggestions(PriceList $priceList): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $this->authorize('update', $priceList);
        abort_if($priceList->is_default || $priceList->adjustment_pct === null, 422, 'Esta lista no admite sugerencias.');

        $defaultList = $tenant->defaultPriceList();
        $recipes = $tenant->recipes()->active()->where('is_semi_elaborate', false)->get();
        $basePrices = RecipePrice::where('price_list_id', $defaultList->id)->whereIn('recipe_id', $recipes->pluck('id'))->pluck('price', 'recipe_id');

        $applied = $this->applySuggestionsBulk(collect([$priceList]), $recipes, $basePrices);

        return back(fallback: route('price-lists.index'))
            ->with('status', "Se aplicaron {$applied} sugerencia(s) en la lista \"{$priceList->name}\".");
    }

    public function applyAllSuggestions(): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $lists = $tenant->priceLists()->active()->where('is_default', false)->whereNotNull('adjustment_pct')->get();
        $defaultList = $tenant->defaultPriceList();
        $recipes = $tenant->recipes()->active()->where('is_semi_elaborate', false)->get();
        $basePrices = RecipePrice::where('price_list_id', $defaultList->id)->whereIn('recipe_id', $recipes->pluck('id'))->pluck('price', 'recipe_id');

        $applied = $this->applySuggestionsBulk($lists, $recipes, $basePrices);

        return back(fallback: route('price-lists.matrix'))
            ->with('status', "Se aplicaron {$applied} sugerencia(s) en todas las listas.");
    }

    /**
     * Aplica en lote las sugerencias de $recipes para cada lista de $lists,
     * escribiendo con upsert()/insert() en vez de un RecipePriceWriter::set()
     * por receta (Q9+Q13). Solo escribe donde no había precio ya cargado.
     *
     * @param  Collection<int, PriceList>  $lists
     * @param  \Illuminate\Database\Eloquent\Collection<int, Recipe>  $recipes
     * @param  Collection<int, string>  $basePrices
     */
    private function applySuggestionsBulk($lists, $recipes, $basePrices): int
    {
        $recipeIds = $recipes->pluck('id');

        // Existentes de TODAS las listas en una sola query, agrupadas por lista,
        // en vez de una query por lista dentro del loop.
        $existingByList = RecipePrice::whereIn('price_list_id', $lists->pluck('id'))
            ->whereIn('recipe_id', $recipeIds)
            ->get()
            ->groupBy('price_list_id')
            ->map(fn ($group) => $group->pluck('price', 'recipe_id'));

        $now = now();
        $rows = [];
        $logRows = [];

        foreach ($lists as $list) {
            $existing = $existingByList->get($list->id, collect());

            foreach ($recipes as $recipe) {
                if ($existing->has($recipe->id) || ! $basePrices->has($recipe->id)) {
                    continue;
                }

                $suggested = round((float) $basePrices->get($recipe->id) * (1 + (float) $list->adjustment_pct / 100), 2);

                $rows[] = [
                    'tenant_id' => $recipe->tenant_id,
                    'price_list_id' => $list->id,
                    'recipe_id' => $recipe->id,
                    'price' => $suggested,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $logRows[] = [
                    'recipe_id' => $recipe->id,
                    'price_list_id' => $list->id,
                    'price' => $suggested,
                    'recorded_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            return 0;
        }

        DB::transaction(function () use ($rows, $logRows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                RecipePrice::upsert($chunk, ['price_list_id', 'recipe_id'], ['price', 'tenant_id']);
            }

            foreach (array_chunk($logRows, 500) as $chunk) {
                RecipePriceLog::insert($chunk);
            }
        });

        return count($rows);
    }
}

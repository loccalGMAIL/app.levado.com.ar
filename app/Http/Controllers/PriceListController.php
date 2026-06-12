<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePriceListRequest;
use App\Http\Requests\UpdatePriceListRequest;
use App\Models\PriceList;
use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostCalculator;
use App\Services\UnitConverter;
use Illuminate\Http\RedirectResponse;
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
            ->when(request('status') === 'active', fn ($q) => $q->where('active', true))
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort, fn ($q) => $q->orderBy($sort, $dir), fn ($q) => $q->orderByDesc('is_default')->orderByDesc('active')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

        return view('price-lists.index', compact('priceLists'));
    }

    public function matrix(): View
    {
        $tenant = app(Tenant::class);
        $tenant->defaultPriceList();

        $priceLists = $tenant->priceLists()
            ->where('active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $defaultList = $priceLists->firstWhere('is_default', true);

        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $recipes = $tenant->recipes()
            ->with([
                'ingredientLines.ingredient',
                'packagingLines.packaging',
                'laborLines.laborType',
                'subrecipeLines.childRecipe',
            ])
            ->where('active', true)
            ->where('is_semi_elaborate', false)
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->orderBy('name', $dir)
            ->paginate(20)
            ->withQueryString();

        $calculator = new RecipeCostCalculator(new UnitConverter);
        $costsPerUnit = collect($recipes->items())
            ->mapWithKeys(fn ($recipe) => [$recipe->id => $calculator->calculate($recipe)['cost_per_unit']]);

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

        return redirect()->route('price-lists.index')->with('status', 'Lista de precios creada.');
    }

    public function update(UpdatePriceListRequest $request, PriceList $priceList): RedirectResponse
    {
        $this->authorizePriceList($priceList);

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

        return redirect()->route('price-lists.index')->with('status', 'Lista de precios actualizada.');
    }

    public function toggleActive(PriceList $priceList): RedirectResponse
    {
        $this->authorizePriceList($priceList);

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

    private function authorizePriceList(PriceList $priceList): void
    {
        abort_unless($priceList->tenant_id === app(Tenant::class)->id, 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Services\RecipeCostCalculator;
use App\Services\UnitConverter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $tenant = app(Tenant::class);
        $calculator = new RecipeCostCalculator(new UnitConverter);

        $tenant->defaultPriceList();
        $priceLists = $tenant->priceLists()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $priceList = $priceLists->firstWhere('id', (int) request('price_list'))
            ?? $priceLists->firstWhere('is_default', true);

        $sortable = ['name', 'selling_price', 'yield_quantity', 'cost_per_unit', 'margin', 'margin_pct'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : 'name';
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $recipes = $tenant->recipes()
            ->with([
                'ingredientLines.ingredient',
                'packagingLines.packaging',
                'laborLines.laborType',
            ])
            ->active()
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->get();

        $prices = RecipePrice::where('price_list_id', $priceList->id)
            ->whereIn('recipe_id', $recipes->pluck('id'))
            ->pluck('price', 'recipe_id');

        $totalFixedCosts = $tenant->totalFixedCosts();
        $productiveHours = $tenant->productive_hours_month ?? 0;
        $overheadPerHour = $tenant->overheadPerHour();

        $allRows = $recipes->map(function ($recipe) use ($calculator, $prices, $overheadPerHour) {
            $costs = $calculator->calculate($recipe);
            $fixedCost = $overheadPerHour !== null ? $costs['total_labor_hours'] * $overheadPerHour : 0.0;
            $totalCost = $costs['total_cost'] + $fixedCost;
            $yieldQty = (float) $recipe->yield_quantity;
            $costPerUnit = $yieldQty > 0 ? $totalCost / $yieldQty : null;
            $sellingPrice = isset($prices[$recipe->id]) ? (float) $prices[$recipe->id] : null;

            $margin = null;
            $marginPct = null;
            if ($sellingPrice !== null && $costPerUnit !== null && $sellingPrice > 0) {
                $margin = $sellingPrice - $costPerUnit;
                $marginPct = ($margin / $sellingPrice) * 100;
            }

            return [
                'recipe' => $recipe,
                'total_cost' => $totalCost,
                'cost_per_unit' => $costPerUnit,
                'selling_price' => $sellingPrice,
                'margin' => $margin,
                'margin_pct' => $marginPct,
            ];
        });

        $sortValue = function (array $row) use ($sort): mixed {
            return match ($sort) {
                'name' => $row['recipe']->name,
                'yield_quantity' => (float) $row['recipe']->yield_quantity,
                'selling_price' => $row['selling_price'],
                'cost_per_unit' => $row['cost_per_unit'],
                'margin' => $row['margin'],
                'margin_pct' => $row['margin_pct'],
                default => $row['recipe']->name,
            };
        };

        $sortedRows = $allRows->sort(function (array $a, array $b) use ($sortValue, $dir): int {
            $aVal = $sortValue($a);
            $bVal = $sortValue($b);

            if ($aVal === null && $bVal === null) {
                return 0;
            }
            if ($aVal === null) {
                return 1;
            }
            if ($bVal === null) {
                return -1;
            }

            $cmp = $aVal <=> $bVal;

            return $dir === 'desc' ? -$cmp : $cmp;
        });

        $perPage = 20;
        $page = max(1, (int) request('page', 1));
        $total = $sortedRows->count();
        $items = $sortedRows->slice(($page - 1) * $perPage, $perPage)->values();

        $recipeRows = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->except('page')],
        );

        $activeRecipeCount = $tenant->recipes()->active()->count();
        $packagingCount = $tenant->packagings()->active()->count();

        return view('dashboard', compact(
            'recipeRows',
            'priceList',
            'priceLists',
            'totalFixedCosts',
            'productiveHours',
            'overheadPerHour',
            'activeRecipeCount',
            'packagingCount',
        ));
    }
}

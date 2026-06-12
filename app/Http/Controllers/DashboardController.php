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
        $priceList = $tenant->defaultPriceList();

        $sortable = ['name', 'selling_price', 'yield_quantity', 'cost_per_unit', 'margin', 'margin_pct'];
        $sort = in_array(request('sort'), $sortable) ? request('sort') : 'name';
        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $recipes = $tenant->recipes()
            ->with([
                'ingredientLines.ingredient',
                'packagingLines.packaging',
                'laborLines.laborType',
            ])
            ->where('active', true)
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->get();

        $prices = RecipePrice::where('price_list_id', $priceList->id)
            ->whereIn('recipe_id', $recipes->pluck('id'))
            ->pluck('price', 'recipe_id');

        $allRows = $recipes->map(function ($recipe) use ($calculator, $prices) {
            $costs = $calculator->calculate($recipe);
            $sellingPrice = isset($prices[$recipe->id]) ? (float) $prices[$recipe->id] : null;
            $costPerUnit = $costs['cost_per_unit'];

            $margin = null;
            $marginPct = null;
            if ($sellingPrice !== null && $costPerUnit !== null && $sellingPrice > 0) {
                $margin = $sellingPrice - $costPerUnit;
                $marginPct = ($margin / $sellingPrice) * 100;
            }

            return [
                'recipe' => $recipe,
                'total_cost' => $costs['total_cost'],
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

        $totalFixedCosts = $tenant->fixedCosts()->where('active', true)->sum('monthly_amount');
        $productiveHours = $tenant->productive_hours_month ?? 0;
        $overheadPerHour = $productiveHours > 0 ? (float) $totalFixedCosts / $productiveHours : null;

        $activeRecipeCount = $tenant->recipes()->where('active', true)->count();
        $packagingCount = $tenant->packagings()->where('active', true)->count();

        return view('dashboard', compact(
            'recipeRows',
            'priceList',
            'totalFixedCosts',
            'productiveHours',
            'overheadPerHour',
            'activeRecipeCount',
            'packagingCount',
        ));
    }
}

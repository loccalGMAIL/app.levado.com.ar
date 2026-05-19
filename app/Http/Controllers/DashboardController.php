<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\RecipeCostCalculator;
use App\Services\UnitConverter;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $tenant = app(Tenant::class);
        $calculator = new RecipeCostCalculator(new UnitConverter);

        $recipes = $tenant->recipes()
            ->with([
                'ingredientLines.ingredient',
                'packagingLines.packaging',
                'laborLines.laborType',
            ])
            ->orderBy('name')
            ->get();

        $recipeRows = $recipes->map(function ($recipe) use ($calculator) {
            $costs = $calculator->calculate($recipe);
            $sellingPrice = $recipe->selling_price !== null ? (float) $recipe->selling_price : null;
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

        $totalFixedCosts = $tenant->fixedCosts()->where('active', true)->sum('monthly_amount');
        $productiveHours = $tenant->productive_hours_month ?? 0;
        $overheadPerHour = $productiveHours > 0 ? (float) $totalFixedCosts / $productiveHours : null;

        $activeRecipeCount = $recipes->where('active', true)->count();
        $packagingCount = $tenant->packagings()->where('active', true)->count();

        return view('dashboard', compact(
            'recipeRows',
            'totalFixedCosts',
            'productiveHours',
            'overheadPerHour',
            'activeRecipeCount',
            'packagingCount',
        ));
    }
}

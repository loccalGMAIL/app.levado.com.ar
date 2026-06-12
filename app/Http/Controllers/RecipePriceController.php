<?php

namespace App\Http\Controllers;

use App\Models\PriceList;
use App\Models\Recipe;
use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Services\RecipeCostCalculator;
use App\Services\RecipePriceWriter;
use App\Services\UnitConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipePriceController extends Controller
{
    public function __construct(
        private readonly RecipePriceWriter $writer,
    ) {}

    public function update(Request $request, Recipe $recipe, PriceList $priceList): JsonResponse
    {
        $tenant = app(Tenant::class);
        abort_unless($recipe->tenant_id === $tenant->id, 403);
        abort_unless($priceList->tenant_id === $tenant->id, 403);
        abort_unless($priceList->active, 422, 'La lista de precios está inactiva.');

        $validated = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $price = $validated['price'] !== null ? (float) $validated['price'] : null;
        $this->writer->set($recipe, $priceList, $price);

        $recipe->load([
            'ingredientLines.ingredient',
            'packagingLines.packaging',
            'laborLines.laborType',
            'subrecipeLines.childRecipe',
        ]);
        $costs = (new RecipeCostCalculator(new UnitConverter))->calculate($recipe);

        $sellingPrice = RecipePrice::where('price_list_id', $priceList->id)
            ->where('recipe_id', $recipe->id)
            ->value('price');
        $sellingPrice = $sellingPrice !== null ? (float) $sellingPrice : null;
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
}

<?php

namespace App\Http\Controllers;

use App\Enums\MarginTier;
use App\Models\PriceList;
use App\Models\Recipe;
use App\Models\RecipePrice;
use App\Models\Tenant;
use App\Services\RecipePriceWriter;
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
        $this->authorize('view', $recipe);
        $this->authorize('view', $priceList);
        abort_unless($priceList->active, 422, 'La lista de precios está inactiva.');

        $validated = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $price = $validated['price'] !== null ? (float) $validated['price'] : null;
        $this->writer->set($recipe, $priceList, $price);

        // Cambiar el precio no altera el costo: alcanza con los caches
        // unit_cost/labor_hours que mantiene RecipeCostPropagator.
        $overheadPerHour = $tenant->overheadPerHour();
        $yieldQty = (float) $recipe->yield_quantity;
        $laborHours = (float) ($recipe->labor_hours ?? 0);
        $fixedCost = $overheadPerHour !== null ? $laborHours * $overheadPerHour : 0.0;
        $costPerUnit = ($recipe->unit_cost !== null && $yieldQty > 0)
            ? (float) $recipe->unit_cost + $fixedCost / $yieldQty
            : null;

        $sellingPrice = RecipePrice::where('price_list_id', $priceList->id)
            ->where('recipe_id', $recipe->id)
            ->value('price');
        $sellingPrice = $sellingPrice !== null ? (float) $sellingPrice : null;

        $margin = null;
        $marginPct = null;

        if ($sellingPrice !== null && $costPerUnit !== null && $sellingPrice > 0) {
            $margin = $sellingPrice - $costPerUnit;
            $marginPct = ($margin / $sellingPrice) * 100;
        }

        return response()->json([
            'selling_price' => $sellingPrice,
            'selling_price_formatted' => $sellingPrice !== null ? number_format($sellingPrice, 2, ',', '.') : null,
            'margin' => $margin,
            'margin_formatted' => $margin !== null ? number_format($margin, 2, ',', '.') : null,
            'margin_pct' => $marginPct,
            'margin_pct_formatted' => $marginPct !== null ? number_format($marginPct, 1, ',', '.') : null,
            // El tramo, no el color: la presentación la resuelve el cliente a
            // partir de él, y los cortes quedan con un solo dueño.
            'margin_tier' => MarginTier::fromPercent($marginPct)->value,
        ]);
    }
}

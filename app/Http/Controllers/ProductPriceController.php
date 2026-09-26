<?php

namespace App\Http\Controllers;

use App\Enums\PricingPolicy;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Services\ProductPriceWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Edición del precio de venta de un artículo en una lista (única fuente: product_prices).
 * Devuelve el margen contra el costo TOTAL del artículo (fullCost, con overhead).
 * Lo consumen las celdas inline del catálogo, el Dashboard y Recetas.
 */
class ProductPriceController extends Controller
{
    public function __construct(private readonly ProductPriceWriter $writer) {}

    public function update(Request $request, Product $product, PriceList $priceList): JsonResponse
    {
        $this->authorize('view', $product);
        $this->authorize('view', $priceList);
        abort_unless($priceList->active, 422, 'La lista de precios está inactiva.');

        $validated = $request->validate([
            'policy_type' => ['nullable', Rule::enum(PricingPolicy::class)],
            'policy_value' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $policy = PricingPolicy::tryFrom($validated['policy_type'] ?? '') ?? PricingPolicy::Manual;

        if ($policy === PricingPolicy::Manual) {
            $this->writer->set($product, $priceList, isset($validated['price']) && $validated['price'] !== null ? (float) $validated['price'] : null);
        } else {
            abort_if(! isset($validated['policy_value']) || $validated['policy_value'] === null, 422, 'Indicá el porcentaje de la política.');
            $this->writer->setPolicy($product, $priceList, $policy, (float) $validated['policy_value']);
        }

        // Costo total (con overhead) para el margen — el elaborado lo deriva de la receta.
        $product->loadMissing('recipe');
        $cost = $product->fullCost(app(Tenant::class)->overheadPerHour() ?? 0.0);
        $sellingPrice = $product->currentPrice($priceList);
        $saved = ProductPrice::where('price_list_id', $priceList->id)->where('product_id', $product->id)->first();

        $margin = null;
        $marginPct = null;
        $marginColor = 'text-masa-madre';

        if ($sellingPrice !== null && $cost !== null && $sellingPrice > 0) {
            $margin = $sellingPrice - $cost;
            $marginPct = $margin / $sellingPrice * 100;
            $marginColor = $marginPct >= 30 ? 'text-green-600' : ($marginPct >= 15 ? 'text-amber-600' : 'text-red-500');
        }

        return response()->json([
            'selling_price' => $sellingPrice,
            'selling_price_formatted' => $sellingPrice !== null ? number_format($sellingPrice, 2, ',', '.') : null,
            'policy_type' => $saved?->policy_type?->value ?? PricingPolicy::Manual->value,
            'policy_value' => $saved && $saved->policy_value !== null ? (float) $saved->policy_value : null,
            'margin' => $margin,
            'margin_formatted' => $margin !== null ? number_format($margin, 2, ',', '.') : null,
            'margin_pct' => $marginPct,
            'margin_pct_formatted' => $marginPct !== null ? number_format($marginPct, 1, ',', '.') : null,
            'margin_color' => $marginColor,
        ]);
    }
}

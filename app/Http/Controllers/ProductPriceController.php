<?php

namespace App\Http\Controllers;

use App\Models\PriceList;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\ProductPriceWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $price = $validated['price'] !== null ? (float) $validated['price'] : null;
        $this->writer->set($product, $priceList, $price);

        // Costo total (con overhead) para el margen — el elaborado lo deriva de la receta.
        $product->loadMissing('recipe');
        $cost = $product->fullCost(app(Tenant::class)->overheadPerHour() ?? 0.0);
        $sellingPrice = $product->currentPrice($priceList);

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
            'margin' => $margin,
            'margin_formatted' => $margin !== null ? number_format($margin, 2, ',', '.') : null,
            'margin_pct' => $marginPct,
            'margin_pct_formatted' => $marginPct !== null ? number_format($marginPct, 1, ',', '.') : null,
            'margin_color' => $marginColor,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Services\ProductPriceWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ProductPriceController extends Controller
{
    public function __construct(private readonly ProductPriceWriter $writer) {}

    /**
     * Matriz de precios de los productos de REVENTA (productos × listas).
     * Paralela a la matriz de recetas: los elaborados se precian en la receta.
     */
    public function matrix(): View
    {
        $tenant = app(Tenant::class);
        $tenant->defaultPriceList();

        $priceLists = $tenant->priceLists()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
        $defaultList = $priceLists->firstWhere('is_default', true);

        $dir = request('dir') === 'desc' ? 'desc' : 'asc';

        $products = $tenant->products()
            ->where('type', ProductType::Resale->value)
            ->active()
            ->when(request('search'), function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->orderBy('name', $dir)
            ->paginate(20)
            ->withQueryString();

        $costsPerUnit = collect($products->items())
            ->mapWithKeys(fn ($product) => [
                $product->id => $product->cost_per_unit !== null ? (float) $product->cost_per_unit : null,
            ]);

        /** @var Collection<int, Collection<int, string>> $prices [product_id][price_list_id] => price */
        $prices = ProductPrice::whereIn('price_list_id', $priceLists->pluck('id'))
            ->whereIn('product_id', collect($products->items())->pluck('id'))
            ->get()
            ->groupBy('product_id')
            ->map(fn ($group) => $group->pluck('price', 'price_list_id'));

        return view('products.prices', compact('priceLists', 'defaultList', 'products', 'costsPerUnit', 'prices'));
    }

    public function update(Request $request, Product $product, PriceList $priceList): JsonResponse
    {
        $this->authorize('view', $product);
        $this->authorize('view', $priceList);
        abort_unless($product->isResale(), 422, 'Solo los productos de reventa se precian acá; los elaborados se precian en su receta.');
        abort_unless($priceList->active, 422, 'La lista de precios está inactiva.');

        $validated = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $price = $validated['price'] !== null ? (float) $validated['price'] : null;
        $this->writer->set($product, $priceList, $price);

        $costPerUnit = $product->cost_per_unit !== null ? (float) $product->cost_per_unit : null;

        $sellingPrice = ProductPrice::where('price_list_id', $priceList->id)
            ->where('product_id', $product->id)
            ->value('price');
        $sellingPrice = $sellingPrice !== null ? (float) $sellingPrice : null;

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

<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkUpdateProductCategoryRequest;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\RedirectResponse;

/**
 * Asignación masiva de categoría desde el catálogo — clasificar de a uno
 * 195 artículos no es viable. Acción propia, no un método más de
 * ProductController: sólo comparte el modelo, no el listado ni el pricing.
 */
class ProductBulkCategoryController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function __invoke(BulkUpdateProductCategoryRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();
        $categoryId = $data['product_category_id'] ?? null;

        // Scopeado por tenant: un id ajeno en product_ids simplemente no
        // matchea y no se toca — misma garantía que un scoped binding, sin
        // validar la pertenencia id por id con N reglas exists.
        $products = $tenant->products()->whereIn('id', $data['product_ids'])->get(['id', 'name']);
        $tenant->products()->whereIn('id', $products->pluck('id'))->update(['product_category_id' => $categoryId]);

        $categoryName = $categoryId !== null
            ? $tenant->productCategories()->find($categoryId)?->name
            : null;

        foreach ($products as $product) {
            $this->recorder->record(
                actor: $request->user(),
                targetType: 'product',
                targetId: $product->id,
                action: 'product.category_assigned',
                payload: ['name' => $product->name, 'category' => $categoryName],
                tenantId: $tenant->id,
            );
        }

        $status = $categoryId === null
            ? "Categoría quitada a {$products->count()} artículo(s)."
            : "{$products->count()} artículo(s) asignados a la categoría.";

        return back(fallback: route('products.index'))->with('status', $status);
    }
}

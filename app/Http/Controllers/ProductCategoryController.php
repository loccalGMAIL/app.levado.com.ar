<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('product_categories', 'name')->where('tenant_id', $tenant->id)],
            'producible' => ['sometimes', 'boolean'],
        ]);

        $category = $tenant->productCategories()->create([
            'name' => $data['name'],
            // El alta rápida por JSON (modal de producto) solo manda name → producible por defecto.
            'producible' => $request->has('producible') ? $request->boolean('producible') : true,
        ]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'product_category',
            targetId: $category->id,
            action: 'product_category.created',
            payload: ['name' => $category->name],
            tenantId: $tenant->id,
        );

        if ($request->wantsJson()) {
            return response()->json(['id' => $category->id, 'name' => $category->name], 201);
        }

        return back(fallback: route('products.index'))
            ->with('reopen_categories', true)
            ->with('status', 'Categoría creada.');
    }

    public function update(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $this->authorizeCategory($productCategory);
        $tenant = app(Tenant::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('product_categories', 'name')->where('tenant_id', $tenant->id)->ignore($productCategory->id)],
            'producible' => ['sometimes', 'boolean'],
        ]);

        $productCategory->update([
            'name' => $data['name'],
            'producible' => $request->boolean('producible'),
        ]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'product_category',
            targetId: $productCategory->id,
            action: 'product_category.updated',
            payload: ['name' => $productCategory->name],
            tenantId: $productCategory->tenant_id,
        );

        return back(fallback: route('products.index'))
            ->with('reopen_categories', true)
            ->with('status', 'Categoría actualizada.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $this->authorizeCategory($productCategory);

        if ($productCategory->products()->exists()) {
            return back(fallback: route('products.index'))
                ->with('reopen_categories', true)
                ->with('category_error', "No se puede eliminar «{$productCategory->name}» porque tiene artículos asignados.");
        }

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'product_category',
            targetId: $productCategory->id,
            action: 'product_category.deleted',
            payload: ['name' => $productCategory->name],
            tenantId: $productCategory->tenant_id,
        );

        $productCategory->delete();

        return back(fallback: route('products.index'))
            ->with('reopen_categories', true)
            ->with('status', 'Categoría eliminada.');
    }

    private function authorizeCategory(ProductCategory $productCategory): void
    {
        abort_unless($productCategory->tenant_id === app(Tenant::class)->id, 403);
    }
}

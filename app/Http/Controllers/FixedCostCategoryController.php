<?php

namespace App\Http\Controllers;

use App\Models\FixedCostCategory;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FixedCostCategoryController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $tenant = app(Tenant::class);
        $category = $tenant->fixedCostCategories()->create($request->only('name'));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'fixed_cost_category',
            targetId: $category->id,
            action: 'fixed_cost_category.created',
            payload: ['name' => $category->name],
            tenantId: $tenant->id,
        );

        if ($request->wantsJson()) {
            return response()->json(['id' => $category->id, 'name' => $category->name], 201);
        }

        return back(fallback: route('fixed-costs.index'))
            ->with('reopen_categories', true)
            ->with('status', 'Categoría creada.');
    }

    public function update(Request $request, FixedCostCategory $fixedCostCategory): RedirectResponse
    {
        $this->authorizeCategory($fixedCostCategory);
        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $fixedCostCategory->update($request->only('name'));

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'fixed_cost_category',
            targetId: $fixedCostCategory->id,
            action: 'fixed_cost_category.updated',
            payload: ['name' => $fixedCostCategory->name],
            tenantId: $fixedCostCategory->tenant_id,
        );

        return back(fallback: route('fixed-costs.index'))
            ->with('reopen_categories', true)
            ->with('status', 'Categoría actualizada.');
    }

    public function destroy(FixedCostCategory $fixedCostCategory): RedirectResponse
    {
        $this->authorizeCategory($fixedCostCategory);

        if ($fixedCostCategory->fixedCosts()->exists()) {
            return back(fallback: route('fixed-costs.index'))
                ->with('reopen_categories', true)
                ->with('category_error', "No se puede eliminar «{$fixedCostCategory->name}» porque tiene gastos asignados.");
        }

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'fixed_cost_category',
            targetId: $fixedCostCategory->id,
            action: 'fixed_cost_category.deleted',
            payload: ['name' => $fixedCostCategory->name],
            tenantId: $fixedCostCategory->tenant_id,
        );

        $fixedCostCategory->delete();

        return back(fallback: route('fixed-costs.index'))
            ->with('reopen_categories', true)
            ->with('status', 'Categoría eliminada.');
    }

    private function authorizeCategory(FixedCostCategory $fixedCostCategory): void
    {
        abort_unless($fixedCostCategory->tenant_id === app(Tenant::class)->id, 403);
    }
}

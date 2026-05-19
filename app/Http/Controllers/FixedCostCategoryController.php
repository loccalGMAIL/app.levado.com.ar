<?php

namespace App\Http\Controllers;

use App\Models\FixedCostCategory;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FixedCostCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:255']]);

        app(Tenant::class)->fixedCostCategories()->create($request->only('name'));

        return redirect()->route('fixed-costs.index')
            ->with('reopen_categories', true)
            ->with('status', 'Categoría creada.');
    }

    public function update(Request $request, FixedCostCategory $fixedCostCategory): RedirectResponse
    {
        $this->authorizeCategory($fixedCostCategory);
        $request->validate(['name' => ['required', 'string', 'max:255']]);

        $fixedCostCategory->update($request->only('name'));

        return redirect()->route('fixed-costs.index')
            ->with('reopen_categories', true)
            ->with('status', 'Categoría actualizada.');
    }

    public function destroy(FixedCostCategory $fixedCostCategory): RedirectResponse
    {
        $this->authorizeCategory($fixedCostCategory);

        if ($fixedCostCategory->fixedCosts()->exists()) {
            return redirect()->route('fixed-costs.index')
                ->with('reopen_categories', true)
                ->with('category_error', "No se puede eliminar «{$fixedCostCategory->name}» porque tiene gastos asignados.");
        }

        $fixedCostCategory->delete();

        return redirect()->route('fixed-costs.index')
            ->with('reopen_categories', true)
            ->with('status', 'Categoría eliminada.');
    }

    private function authorizeCategory(FixedCostCategory $fixedCostCategory): void
    {
        abort_unless($fixedCostCategory->tenant_id === app(Tenant::class)->id, 403);
    }
}

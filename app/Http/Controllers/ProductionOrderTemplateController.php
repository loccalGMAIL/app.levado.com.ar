<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\ProductionOrderDuplicator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Plantillas preestablecidas de orden de producción: viven en la misma tabla
 * de production_orders (is_template=true, sin fecha) y quedan excluidas del
 * listado normal por el global scope del modelo. El route-model-binding
 * implícito de Eloquent aplicaría ese mismo scope y nunca las resolvería, así
 * que acá se toma el id como entero plano y se resuelve a mano con
 * onlyTemplates() — sigue siendo tenant-safe porque BelongsToTenant es un
 * scope aparte que sigue activo.
 */
class ProductionOrderTemplateController extends Controller
{
    public function __construct(private readonly ProductionOrderDuplicator $duplicator) {}

    public function index(): View
    {
        $templates = app(Tenant::class)->productionOrders()->onlyTemplates()->orderBy('name')->get();

        return view('production-orders.templates', compact('templates'));
    }

    public function use(Request $request, int $template): RedirectResponse
    {
        $order = app(Tenant::class)->productionOrders()->onlyTemplates()->findOrFail($template);

        $data = $request->validate(['scheduled_for' => ['nullable', 'date']]);

        $copy = $this->duplicator->duplicate(
            $order,
            $request->user(),
            scheduledFor: $data['scheduled_for'] ?? now()->toDateString(),
        );

        return redirect()->route('production-orders.show', $copy)->with('status', 'Orden creada desde la plantilla.');
    }

    public function destroy(int $template): RedirectResponse
    {
        $order = app(Tenant::class)->productionOrders()->onlyTemplates()->findOrFail($template);
        $order->delete();

        return back(fallback: route('production-order-templates.index'))->with('status', 'Plantilla eliminada.');
    }
}

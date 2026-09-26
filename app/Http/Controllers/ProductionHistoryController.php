<?php

namespace App\Http\Controllers;

use App\Enums\ProductionStatus;
use App\Models\Tenant;
use Illuminate\View\View;

/**
 * Tabla global de producciones de todos los artículos — la pestaña
 * "Historial" de Artículos. Reemplaza al viejo listado /production (retirado
 * junto con "Producir suelto"): agrupa lo que antes eran renglones sueltos
 * mezclados de todos los artículos, con filtro por artículo/estado/fecha.
 *
 * Controller propio (no un método más de ProductController): la tabla es de
 * Production, no de Product, y ProductController inyecta dependencias
 * (ProductCodeAssigner, ArticlePriceRecalculator...) que no aplican acá.
 */
class ProductionHistoryController extends Controller
{
    public function index(): View
    {
        $tenant = app(Tenant::class);

        $productions = $tenant->productions()
            ->with(['product', 'user', 'productionOrder'])
            ->when(request('product'), fn ($q, $id) => $q->where('product_id', $id))
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('from'), fn ($q, $date) => $q->whereDate('produced_at', '>=', $date))
            ->when(request('to'), fn ($q, $date) => $q->whereDate('produced_at', '<=', $date))
            ->latest('produced_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $products = $tenant->products()->orderBy('name')->get();
        $statuses = ProductionStatus::cases();

        return view('products.history', compact('productions', 'products', 'statuses'));
    }
}

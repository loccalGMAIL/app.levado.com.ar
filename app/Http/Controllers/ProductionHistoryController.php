<?php

namespace App\Http\Controllers;

use App\Enums\ProductionStatus;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
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
            ->tap(fn (Builder $q) => $this->sortListing($q, [
                'produced_at' => 'produced_at',
                'product' => Product::select('name')->whereColumn('products.id', 'productions.product_id'),
                'quantity' => 'quantity',
                'total_cost' => 'total_cost',
                // Las producciones ad-hoc (sin orden) quedan juntas al principio en ascendente.
                'origin' => ProductionOrder::select('number')->whereColumn('production_orders.id', 'productions.production_order_id'),
                'status' => 'status',
            ], fn (Builder $q) => $q->latest('produced_at')->latest('id'), 'productions.id'))
            ->paginate($this->perPage())
            ->withQueryString();

        $products = $tenant->products()->orderBy('name')->get();
        $statuses = ProductionStatus::cases();

        return view('products.history', compact('productions', 'products', 'statuses'));
    }
}

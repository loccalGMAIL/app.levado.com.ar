<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCostLog;
use App\Models\Production;
use Illuminate\Http\JsonResponse;

class ProductCostHistoryController extends Controller
{
    /**
     * Historial de costo de un artículo, para el panel del catálogo.
     *
     * Va por JSON a demanda (mismo criterio que ProductionController::preview) en
     * vez de viajar en el payload del listado: son N filas por artículo y el HTML
     * del catálogo se inflaría para algo que casi nunca se abre.
     *
     * Reventa: sus logs de costo (compra o carga manual). Elaborado: sus
     * fabricaciones — el costo VIGENTE del elaborado sigue saliendo de la
     * receta (Product::currentCost()), esto solo muestra lo que costó cada
     * vez que se produjo.
     */
    public function index(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $rows = $product->isResale()
            ? $this->resaleRows($product)
            : $this->productionRows($product);

        return response()->json([
            'product' => $product->name,
            'is_resale' => $product->isResale(),
            'rows' => $rows,
        ]);
    }

    private function resaleRows(Product $product): array
    {
        return $product->costLogs()
            ->with('purchaseLine.purchase.supplier')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (ProductCostLog $log) {
                $purchase = $log->purchaseLine?->purchase;

                return [
                    'cost' => (float) $log->cost_per_unit,
                    'cost_formatted' => number_format((float) $log->cost_per_unit, 2, ',', '.'),
                    'source' => $log->source->value,
                    'source_label' => $log->source->label(),
                    'recorded_at' => $log->recorded_at->format('d/m/Y H:i'),
                    'supplier' => $purchase?->supplier?->name,
                    'purchase_url' => $purchase !== null ? route('purchases.show', $purchase) : null,
                    'production_url' => null,
                    'production_label' => null,
                    'cancelled' => false,
                ];
            })
            ->all();
    }

    private function productionRows(Product $product): array
    {
        return $product->productions()
            ->orderByDesc('produced_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(function (Production $production) {
                return [
                    'cost' => (float) $production->unit_cost,
                    'cost_formatted' => number_format((float) $production->unit_cost, 2, ',', '.'),
                    'source' => 'produccion',
                    'source_label' => 'Producción',
                    'recorded_at' => $production->produced_at->format('d/m/Y H:i'),
                    'supplier' => null,
                    'purchase_url' => null,
                    'production_url' => route('production.show', $production),
                    'production_label' => number_format((float) $production->quantity, 2, ',', '.').' '.$production->unit->short(),
                    'cancelled' => $production->isCancelled(),
                ];
            })
            ->all();
    }
}

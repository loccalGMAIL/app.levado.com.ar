<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCostLog;
use Illuminate\Http\JsonResponse;

class ProductCostHistoryController extends Controller
{
    /**
     * Historial de costo de un artículo, para el panel del catálogo.
     *
     * Va por JSON a demanda en vez de viajar en el payload del listado: son N
     * filas por artículo y el HTML del catálogo se inflaría para algo que casi
     * nunca se abre.
     *
     * Reventa: sus logs de costo (compra o carga manual). Elaborado: sin
     * historial propio — su costo vigente sale de la receta (Product::currentCost()).
     */
    public function index(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return response()->json([
            'product' => $product->name,
            'is_resale' => $product->isResale(),
            'rows' => $product->isResale() ? $this->resaleRows($product) : [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
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
                ];
            })
            ->all();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCostLog;
use Illuminate\Http\JsonResponse;

class ProductCostHistoryController extends Controller
{
    /**
     * Historial de costo de un artículo de reventa, para el panel del catálogo.
     *
     * Va por JSON a demanda (mismo criterio que ProductionController::preview) en
     * vez de viajar en el payload del listado: son N filas por artículo y el HTML
     * del catálogo se inflaría para algo que casi nunca se abre.
     */
    public function index(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $rows = $product->costLogs()
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
            });

        return response()->json([
            'product' => $product->name,
            'is_resale' => $product->isResale(),
            'rows' => $rows,
        ]);
    }
}

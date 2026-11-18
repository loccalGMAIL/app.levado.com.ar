<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repite el backfill de `products.order_sheet_id` para los artículos que
     * quedaron sin destino: los que están en la lista de una única planilla
     * pasan a apuntar a ella. No toca a los que ya tienen destino.
     */
    public function up(): void
    {
        $single = DB::table('order_sheet_lines')
            ->join('order_sheet_weeks', 'order_sheet_weeks.id', '=', 'order_sheet_lines.order_sheet_week_id')
            ->groupBy('order_sheet_lines.product_id')
            ->havingRaw('COUNT(DISTINCT order_sheet_weeks.order_sheet_id) = 1')
            ->select('order_sheet_lines.product_id', DB::raw('MIN(order_sheet_weeks.order_sheet_id) as order_sheet_id'))
            ->get();

        foreach ($single as $row) {
            DB::table('products')
                ->where('id', $row->product_id)
                ->whereNull('order_sheet_id')
                ->update(['order_sheet_id' => $row->order_sheet_id]);
        }
    }

    public function down(): void
    {
        // Datos derivados: no hay nada que deshacer.
    }
};

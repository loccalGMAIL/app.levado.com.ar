<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Planilla de pedidos a la que suma lo que piden los clientes de este
     * artículo. Los artículos que ya están en la lista de una única planilla
     * arrancan apuntando a ella.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('order_sheet_id')->nullable()->after('product_category_id')
                ->constrained('order_sheets')->nullOnDelete();
        });

        $single = DB::table('order_sheet_lines')
            ->join('order_sheet_weeks', 'order_sheet_weeks.id', '=', 'order_sheet_lines.order_sheet_week_id')
            ->groupBy('order_sheet_lines.product_id')
            ->havingRaw('COUNT(DISTINCT order_sheet_weeks.order_sheet_id) = 1')
            ->select('order_sheet_lines.product_id', DB::raw('MIN(order_sheet_weeks.order_sheet_id) as order_sheet_id'))
            ->get();

        foreach ($single as $row) {
            DB::table('products')->where('id', $row->product_id)->update(['order_sheet_id' => $row->order_sheet_id]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_sheet_id');
        });
    }
};

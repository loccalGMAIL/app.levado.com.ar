<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La percepción pasa de alícuota por renglón a un listado propio de la compra.
     * En los datos existentes la alícuota nunca varía entre renglones de una misma
     * compra, así que una fila por compra es exacta; el importe se congela desde los
     * renglones (amount_is_manual) para que el histórico no se mueva al editar la compra.
     */
    public function up(): void
    {
        DB::table('purchase_lines')
            ->where('percepcion_rate', '>', 0)
            ->selectRaw('purchase_id, max(percepcion_rate) as rate, sum(subtotal * percepcion_rate / 100) as amount')
            ->groupBy('purchase_id')
            ->orderBy('purchase_id')
            ->get()
            ->each(function ($row) {
                DB::table('purchase_perceptions')->insert([
                    'purchase_id' => $row->purchase_id,
                    'type' => 'otro',
                    'description' => 'Percepción',
                    'base_amount' => null,
                    'rate' => $row->rate,
                    'amount' => round((float) $row->amount, 2),
                    'amount_is_manual' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropColumn('percepcion_rate');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('default_percepcion_rate');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->decimal('default_percepcion_rate', 5, 2)->nullable()->after('default_iva_rate');
        });

        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->decimal('percepcion_rate', 5, 2)->nullable()->after('iva_rate');
        });

        DB::table('purchase_perceptions')->orderBy('id')->get()->each(function ($row) {
            if ($row->rate !== null) {
                DB::table('purchase_lines')
                    ->where('purchase_id', $row->purchase_id)
                    ->update(['percepcion_rate' => min((float) $row->rate, 999.99)]);
            }
        });
    }
};

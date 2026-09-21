<?php

use App\Models\FixedCostLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * fixed_cost_logs pasa de "una fecha de vigencia suelta" a "un monto por
     * mes calendario". El backfill agrupa los logs existentes por
     * (fixed_cost_id, mes de valid_from) y conserva el de id más alto de cada
     * grupo -el último cambio del mes es el que rigió al cerrarlo-, porque un
     * unique(['fixed_cost_id', 'period']) no puede convivir con duplicados.
     */
    public function up(): void
    {
        Schema::table('fixed_cost_logs', function (Blueprint $table) {
            $table->date('period')->nullable()->after('monthly_amount');
        });

        $survivorIdPerGroup = [];
        FixedCostLog::query()->orderBy('id')->get(['id', 'fixed_cost_id', 'valid_from'])
            ->each(function (FixedCostLog $log) use (&$survivorIdPerGroup) {
                $period = Carbon::parse($log->valid_from)->startOfMonth()->toDateString();
                $survivorIdPerGroup["{$log->fixed_cost_id}:{$period}"] = ['id' => $log->id, 'period' => $period];
            });

        foreach ($survivorIdPerGroup as $survivor) {
            FixedCostLog::whereKey($survivor['id'])->update(['period' => $survivor['period']]);
        }

        $survivorIds = array_column($survivorIdPerGroup, 'id');
        FixedCostLog::query()->whereNotIn('id', $survivorIds)->delete();

        // El unique nuevo se crea ANTES de tocar el índice viejo:
        // fixed_cost_id, además de agrupar los logs, sostiene la foreign key
        // hacia fixed_costs. MySQL se niega a borrar el único índice que la
        // respalda; con el unique ya puesto (también empieza por
        // fixed_cost_id) queda otro índice disponible y el drop no falla.
        Schema::table('fixed_cost_logs', function (Blueprint $table) {
            $table->unique(['fixed_cost_id', 'period']);
        });

        Schema::table('fixed_cost_logs', function (Blueprint $table) {
            $table->dropIndex(['fixed_cost_id', 'valid_from']);
            $table->dropColumn('valid_from');
        });

        Schema::table('fixed_cost_logs', function (Blueprint $table) {
            $table->date('period')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_cost_logs', function (Blueprint $table) {
            $table->date('valid_from')->nullable()->after('monthly_amount');
        });

        FixedCostLog::query()->update(['valid_from' => DB::raw('period')]);

        // Mismo orden que en up(), invertido: el índice viejo se recrea antes
        // de soltar el unique nuevo, para que fixed_cost_id nunca se quede
        // sin ningún índice que respalde la foreign key.
        Schema::table('fixed_cost_logs', function (Blueprint $table) {
            $table->index(['fixed_cost_id', 'valid_from']);
        });

        Schema::table('fixed_cost_logs', function (Blueprint $table) {
            $table->dropUnique(['fixed_cost_id', 'period']);
            $table->dropColumn('period');
        });
    }
};

<?php

namespace App\Services;

use App\Models\FixedCost;
use App\Models\FixedCostLog;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Único dueño de la lectura del histórico mensual de gastos fijos
 * (`fixed_cost_logs`). No reemplaza a `Tenant::totalFixedCosts()` /
 * `overheadPerHour()` -esos siguen siendo la fórmula vigente que alimenta el
 * costeo de recetas- sino que responde "cuánto regía en tal mes", con
 * arrastre: un gasto sin registro propio en el período vale lo último
 * cargado antes de esa fecha.
 */
class FixedCostHistory
{
    /**
     * Monto vigente de cada gasto fijo del tenant en un mes dado, indexado
     * por fixed_cost_id.
     *
     * @return Collection<int, array{amount: float, source_period: Carbon, carried: bool}>
     */
    public function amountsForPeriod(Tenant $tenant, Carbon $period): Collection
    {
        $period = $period->copy()->startOfMonth();
        $periodBound = $period->toDateString();

        $rows = DB::table('fixed_cost_logs as l')
            ->join('fixed_costs as f', 'f.id', '=', 'l.fixed_cost_id')
            ->where('f.tenant_id', $tenant->id)
            // Bound como string 'Y-m-d', no objeto Carbon: un where() plano no
            // pasa por `fromDateTime()`, así que un Carbon se ligaría con su
            // propio __toString() (con hora) en vez del formato en que
            // 'period' quedó guardado -mismo problema que en record().
            ->where('l.period', '<=', $periodBound)
            ->whereRaw(
                'l.period = (select max(l2.period) from fixed_cost_logs l2 '.
                'where l2.fixed_cost_id = l.fixed_cost_id and l2.period <= ?)',
                [$periodBound]
            )
            ->select('l.fixed_cost_id', 'l.monthly_amount', 'l.period')
            ->get();

        return $rows->mapWithKeys(function ($row) use ($period) {
            $sourcePeriod = Carbon::parse($row->period)->startOfMonth();

            return [(int) $row->fixed_cost_id => [
                'amount' => (float) $row->monthly_amount,
                'source_period' => $sourcePeriod,
                'carried' => ! $sourcePeriod->equalTo($period),
            ]];
        });
    }

    public function totalForPeriod(Tenant $tenant, Carbon $period): float
    {
        return (float) $this->amountsForPeriod($tenant, $period)->sum('amount');
    }

    /**
     * Total de gastos fijos por cada uno de los últimos $months meses,
     * terminando en el mes en curso.
     *
     * @return Collection<int, array{period: Carbon, total: float}>
     */
    public function monthlyTotals(Tenant $tenant, int $months): Collection
    {
        $currentMonth = Carbon::now()->startOfMonth();

        return collect(range($months - 1, 0))
            ->map(fn (int $monthsAgo) => $currentMonth->copy()->subMonths($monthsAgo))
            ->map(fn (Carbon $period) => [
                'period' => $period,
                'total' => $this->totalForPeriod($tenant, $period),
            ])
            ->values();
    }

    /**
     * Línea de tiempo de un gasto fijo: un punto por mes con registro propio,
     * con la variación porcentual contra el registro anterior.
     *
     * @return Collection<int, array{period: Carbon, amount: float, change_pct: ?float}>
     */
    public function timelineFor(FixedCost $fixedCost): Collection
    {
        return $this->withChangePct(
            $fixedCost->logs()->orderBy('period')->get()
                ->map(fn (FixedCostLog $log) => ['period' => $log->period, 'amount' => (float) $log->monthly_amount])
        );
    }

    /**
     * Timelines de varios gastos en UNA sola query, para no repetir
     * timelineFor() gasto por gasto (N+1) en un reporte. Indexado por
     * fixed_cost_id; cada gasto sin logs propios en la ventana simplemente
     * no aparece en la colección resultante.
     *
     * @param  list<int>  $fixedCostIds
     * @return Collection<int, Collection<int, array{period: Carbon, amount: float, change_pct: ?float}>>
     */
    public function timelinesFor(array $fixedCostIds, Carbon $until, int $months): Collection
    {
        if ($fixedCostIds === []) {
            return collect();
        }

        $since = $until->copy()->startOfMonth()->subMonths($months - 1);

        return FixedCostLog::query()
            ->whereIn('fixed_cost_id', $fixedCostIds)
            ->where('period', '>=', $since->toDateString())
            ->where('period', '<=', $until->copy()->startOfMonth()->toDateString())
            ->orderBy('fixed_cost_id')
            ->orderBy('period')
            ->get()
            ->groupBy('fixed_cost_id')
            ->map(fn (Collection $logs) => $this->withChangePct(
                $logs->map(fn (FixedCostLog $log) => ['period' => $log->period, 'amount' => (float) $log->monthly_amount])
            ));
    }

    /**
     * Agrega `change_pct` (variación % contra el punto anterior de la misma
     * serie) a una secuencia ya ordenada por período. Única fórmula del
     * cálculo -timelineFor() y timelinesFor() la comparten- para no
     * divergir en cómo se redondea o en el caso base sin punto anterior.
     *
     * @param  Collection<int, array{period: Carbon, amount: float}>  $points
     * @return Collection<int, array{period: Carbon, amount: float, change_pct: ?float}>
     */
    private function withChangePct(Collection $points): Collection
    {
        $previousAmount = null;

        return $points->map(function (array $point) use (&$previousAmount) {
            $changePct = ($previousAmount !== null && $previousAmount != 0.0)
                ? (($point['amount'] - $previousAmount) / $previousAmount) * 100
                : null;
            $previousAmount = $point['amount'];

            return [...$point, 'change_pct' => $changePct];
        })->values();
    }

    /**
     * Registra (o corrige) el monto de un gasto fijo para un mes puntual.
     * Idempotente por (fixed_cost_id, period): guardar el mismo mes dos
     * veces actualiza el registro existente en vez de duplicarlo.
     */
    public function record(FixedCost $fixedCost, Carbon $period, float $amount): void
    {
        // El valor de búsqueda de updateOrCreate() va a un where() plano, que
        // no pasa por `fromDateTime()` como sí hace guardar un atributo. Un
        // objeto Carbon ahí se serializa distinto a como quedó guardado
        // 'period' en la fila existente, así que nunca matchea: siempre
        // intenta crear, y choca con el unique(fixed_cost_id, period).
        $fixedCost->logs()->updateOrCreate(
            ['period' => $period->copy()->startOfMonth()->toDateString()],
            ['monthly_amount' => $amount],
        );
    }

    /**
     * "Septiembre 2026". No se usa `translatedFormat()`: el proyecto no fija
     * `Carbon::setLocale()` en ningún lado, así que devolvería nombres de mes
     * en inglés pese a `config('app.locale') === 'es'`.
     */
    public static function periodLabel(Carbon $period): string
    {
        static $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $months[(int) $period->format('n')].' '.$period->format('Y');
    }
}

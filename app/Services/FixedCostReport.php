<?php

namespace App\Services;

use App\Models\FixedCost;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Único dueño del armado de datos del reporte imprimible de gastos
 * (fixed-costs/report). Compone secciones ya existentes -FixedCostHistory
 * para el histórico, VariableExpense::scopeBetween() para los gastos
 * variables- sin repetir su SQL, y devuelve sólo arrays/escalares: la vista
 * del reporte se renderiza también para dompdf, y una excepción por lazy
 * loading ahí adentro deja un PDF corrupto en vez de un error legible.
 *
 * Tope de `details`: el reporte no imprime línea de tiempo de más de 50
 * gastos (los de mayor monto vigente), para no disparar un PDF gigante ni
 * una query de miles de filas por un tenant con muchos gastos fijos.
 */
class FixedCostReport
{
    private const MAX_DETAILS = 50;

    public function __construct(private readonly FixedCostHistory $history) {}

    /**
     * @param  array{from: Carbon, to: Carbon, sections: list<string>, search: ?string, status: ?string, category: ?int, ve_search: ?string, ve_category: list<int>, ve_supplier: ?int}  $options
     * @return array{
     *     business: array{name: string, razon_social: ?string, cuit: ?string, condicion_iva: ?string, currency: string, logo: ?string},
     *     meta: array{from: string, to: string, from_ymd: string, to_ymd: string, snapshot_label: string, generated_at: string, months: int, sections: list<string>, filters: array{search: ?string, status: ?string, category: ?string, ve_search: ?string, ve_category: ?string, ve_supplier: ?string}},
     *     current: array{rows: list<array{id: int, name: string, category: ?string, active: bool, amount: float, carried: bool}>, total: float, count: int},
     *     monthly: ?array{rows: list<array{label: string, total: float, change_pct: ?float}>, average: float, min: float, max: float},
     *     details: ?array{rows: list<array{name: string, category: ?string, timeline: list<array{label: string, amount: float, change_pct: ?float}>}>, truncated: bool, total_count: int},
     *     variable: ?array{rows: list<array{date: string, name: string, category: ?string, supplier: ?string, description: ?string, amount: float}>, total: float},
     *     totals: array{fixed: float, variable: float, grand: float, productive_hours: ?int, overhead_per_hour: ?float},
     * }
     */
    public function build(Tenant $tenant, array $options): array
    {
        $from = $options['from'];
        $to = $options['to'];
        $fromMonth = $from->copy()->startOfMonth();
        $toMonth = $to->copy()->startOfMonth();
        $months = $fromMonth->diffInMonths($toMonth) + 1;
        $sections = $options['sections'];

        $current = $this->buildCurrent($tenant, $toMonth, $options['search'], $options['status'], $options['category']);
        $monthly = in_array('monthly', $sections, true) ? $this->buildMonthly($tenant, $fromMonth, $toMonth) : null;
        $details = in_array('details', $sections, true) ? $this->buildDetails($tenant, $current['rows'], $toMonth, $months) : null;
        $variable = in_array('variable', $sections, true)
            ? $this->buildVariable($tenant, $from, $to, $options['ve_search'], $options['ve_category'], $options['ve_supplier'])
            : null;

        return [
            'business' => [
                'name' => $tenant->name,
                'razon_social' => $tenant->razon_social,
                'cuit' => $tenant->cuit,
                'condicion_iva' => $tenant->condicion_iva?->label(),
                'currency' => $tenant->currency ?? 'ARS',
                'logo' => $this->logoDataUri($tenant),
            ],
            'meta' => [
                'from' => $from->format('d/m/Y'),
                'to' => $to->format('d/m/Y'),
                'from_ymd' => $from->format('Y-m-d'),
                'to_ymd' => $to->format('Y-m-d'),
                'snapshot_label' => FixedCostHistory::periodLabel($toMonth),
                'generated_at' => Carbon::now()->format('d/m/Y H:i'),
                'months' => $months,
                'sections' => $sections,
                // Nombres resueltos, no ids: es lo que se imprime en el encabezado del
                // reporte, y un id sin cruzar contra el catálogo no le dice nada al lector.
                'filters' => [
                    'search' => $options['search'],
                    'status' => $options['status'],
                    'category' => $options['category'] ? $tenant->fixedCostCategories()->find($options['category'])?->name : null,
                    've_search' => $options['ve_search'],
                    've_category' => $options['ve_category']
                        ? $tenant->variableExpenseCategories()->whereIn('id', $options['ve_category'])->pluck('name')->implode(', ')
                        : null,
                    've_supplier' => $options['ve_supplier'] ? $tenant->suppliers()->find($options['ve_supplier'])?->name : null,
                ],
            ],
            'current' => $current,
            'monthly' => $monthly,
            'details' => $details,
            'variable' => $variable,
            'totals' => [
                'fixed' => $current['total'],
                'variable' => $variable['total'] ?? 0.0,
                'grand' => $current['total'] + ($variable['total'] ?? 0.0),
                'productive_hours' => $tenant->productive_hours_month,
                'overhead_per_hour' => $tenant->overheadPerHour(),
            ],
        ];
    }

    /**
     * @return array{rows: list<array{id: int, name: string, category: ?string, active: bool, amount: float, carried: bool}>, total: float, count: int}
     */
    private function buildCurrent(Tenant $tenant, Carbon $period, ?string $search, ?string $status, ?int $category): array
    {
        // Mismo when(search)/when(status)/when(category) que FixedCostController@index,
        // para que "Aplicar los filtros de la pantalla" reporte exactamente lo que el
        // usuario está viendo en Gastos Fijos.
        $fixedCosts = $tenant->fixedCosts()
            ->with('category')
            ->when($search, function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $q->where('name', 'like', "%{$escaped}%");
            })
            ->when($status === 'active', fn ($q) => $q->active())
            ->when($status === 'inactive', fn ($q) => $q->where('active', false))
            ->when($category, fn ($q) => $q->where('fixed_cost_category_id', $category))
            ->orderByDesc('active')->orderBy('name')
            ->get();

        $amounts = $this->history->amountsForPeriod($tenant, $period);

        $rows = $fixedCosts->map(fn (FixedCost $fixedCost) => [
            'id' => $fixedCost->id,
            'name' => $fixedCost->name,
            'category' => $fixedCost->category?->name,
            'active' => $fixedCost->active,
            'amount' => $amounts->get($fixedCost->id)['amount'] ?? 0.0,
            'carried' => $amounts->get($fixedCost->id)['carried'] ?? false,
        ])->values()->all();

        return [
            'rows' => $rows,
            'total' => array_sum(array_column($rows, 'amount')),
            'count' => count($rows),
        ];
    }

    /**
     * No reusa `FixedCostHistory::monthlyTotals()`: esa ventana termina
     * siempre en el mes en curso (`Carbon::now()`), y acá la ventana es
     * exactamente `[fromMonth, toMonth]` -el rango que eligió el usuario,
     * que puede terminar en el pasado-. Reusa sí `totalForPeriod()`, mes a
     * mes, que es el cálculo real.
     *
     * @return array{rows: list<array{label: string, total: float, change_pct: ?float}>, average: float, min: float, max: float}
     */
    private function buildMonthly(Tenant $tenant, Carbon $fromMonth, Carbon $toMonth): array
    {
        $months = $fromMonth->diffInMonths($toMonth) + 1;
        $previousTotal = null;

        $rows = collect(range(0, $months - 1))
            ->map(fn (int $i) => $fromMonth->copy()->addMonths($i))
            ->map(function (Carbon $point) use ($tenant, &$previousTotal) {
                $total = $this->history->totalForPeriod($tenant, $point);
                $changePct = ($previousTotal !== null && $previousTotal != 0.0)
                    ? (($total - $previousTotal) / $previousTotal) * 100
                    : null;
                $previousTotal = $total;

                return [
                    'label' => FixedCostHistory::periodLabel($point),
                    'total' => $total,
                    'change_pct' => $changePct,
                ];
            })
            ->values();

        $totals = $rows->pluck('total');

        return [
            'rows' => $rows->all(),
            'average' => $totals->isNotEmpty() ? (float) $totals->average() : 0.0,
            'min' => $totals->isNotEmpty() ? (float) $totals->min() : 0.0,
            'max' => $totals->isNotEmpty() ? (float) $totals->max() : 0.0,
        ];
    }

    /**
     * @param  list<array{id: int, name: string, category: ?string, active: bool, amount: float, carried: bool}>  $currentRows
     * @return array{rows: list<array{name: string, category: ?string, timeline: list<array{label: string, amount: float, change_pct: ?float}>}>, truncated: bool, total_count: int}
     */
    private function buildDetails(Tenant $tenant, array $currentRows, Carbon $toMonth, int $months): array
    {
        $ordered = collect($currentRows)->sortByDesc('amount')->values();
        $truncated = $ordered->count() > self::MAX_DETAILS;
        $selected = $ordered->take(self::MAX_DETAILS);

        $timelines = $this->history->timelinesFor($selected->pluck('id')->all(), $toMonth, $months);

        $rows = $selected->map(fn (array $row) => [
            'name' => $row['name'],
            'category' => $row['category'],
            'timeline' => ($timelines->get($row['id']) ?? collect())
                ->map(fn (array $point) => [
                    'label' => FixedCostHistory::periodLabel($point['period']),
                    'amount' => $point['amount'],
                    'change_pct' => $point['change_pct'],
                ])->all(),
        ])->values()->all();

        return [
            'rows' => $rows,
            'truncated' => $truncated,
            'total_count' => $ordered->count(),
        ];
    }

    /**
     * A diferencia de `current`/`monthly`/`details` -que trabajan a mes
     * completo, la única granularidad que tiene un gasto fijo-, acá se usan
     * `from`/`to` tal cual los eligió el usuario: `VariableExpense` tiene
     * fecha propia por día, y forzarla a los bordes del mes calendario sólo
     * perdía precisión sin necesidad.
     *
     * @param  list<int>  $categoryIds
     * @return array{rows: list<array{date: string, name: string, category: ?string, supplier: ?string, description: ?string, amount: float}>, total: float}
     */
    private function buildVariable(Tenant $tenant, Carbon $from, Carbon $to, ?string $search, array $categoryIds, ?int $supplier): array
    {
        // Mismo when(search)/when(category)/when(supplier) que VariableExpenseController@index,
        // para que "Aplicar los filtros de la pantalla" (abierto desde Gastos Variables) reporte
        // exactamente lo que el usuario está viendo ahí.
        $expenses = $tenant->variableExpenses()
            ->with(['category', 'supplier'])
            ->when($search, function ($q, $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                // El closure agrupa el OR: suelto, se mezclaría con category/supplier/fecha
                // y el filtro de búsqueda los ignoraría.
                return $q->where(function ($q2) use ($escaped) {
                    $q2->where('name', 'like', "%{$escaped}%")
                        ->orWhere('description', 'like', "%{$escaped}%");
                });
            })
            ->when($categoryIds, fn ($q) => $q->whereIn('variable_expense_category_id', $categoryIds))
            ->when($supplier, fn ($q) => $q->where('supplier_id', $supplier))
            ->between($from->toDateString(), $to->toDateString())
            ->orderBy('expense_date')
            ->get();

        $rows = $expenses->map(fn ($expense) => [
            'date' => $expense->expense_date->format('d/m/Y'),
            'name' => $expense->name,
            'category' => $expense->category?->name,
            'supplier' => $expense->supplier?->name,
            'description' => $expense->description,
            'amount' => (float) $expense->amount,
        ])->values()->all();

        return [
            'rows' => $rows,
            'total' => array_sum(array_column($rows, 'amount')),
        ];
    }

    /**
     * dompdf no acepta `Storage::url()` (necesita filesystem local o data
     * URI, no una URL relativa que dependa de APP_URL). Se resuelve acá una
     * sola vez y sirve para las dos salidas -pantalla y PDF- sin ramas por
     * formato en la vista.
     */
    private function logoDataUri(Tenant $tenant): ?string
    {
        if (! $tenant->logo_path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($tenant->logo_path) || $disk->size($tenant->logo_path) > 512 * 1024) {
            return null;
        }

        $mime = $disk->mimeType($tenant->logo_path);

        // dompdf no renderiza SVG ni WebP; sin un tipo soportado, cae al
        // fallback de nombre del negocio en vez de dejar un hueco roto.
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($tenant->logo_path));
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\FixedCostReportRequest;
use App\Models\Tenant;
use App\Services\FixedCostReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FixedCostReportController extends Controller
{
    public function __construct(private readonly FixedCostReport $report) {}

    public function show(FixedCostReportRequest $request): View
    {
        $report = $this->report->build(app(Tenant::class), $request->options());

        return view('fixed-costs.report.screen', compact('report'));
    }

    public function download(FixedCostReportRequest $request): Response
    {
        $report = $this->report->build(app(Tenant::class), $request->options());

        // Sólo acá: un reporte con `details` sobre muchos meses/gastos puede
        // superar los límites por defecto del proceso PHP. No se toca el
        // límite global -sólo el de esta request, que ya viene acotada por
        // los topes de FixedCostReportRequest/FixedCostReport (months ≤ 24,
        // details ≤ 50 gastos)-.
        ini_set('memory_limit', '256M');
        set_time_limit(60);

        return Pdf::loadView('fixed-costs.report.pdf', compact('report'))
            ->setPaper('a4')
            ->download("gastos-{$report['meta']['from_ymd']}_a_{$report['meta']['to_ymd']}.pdf");
    }
}

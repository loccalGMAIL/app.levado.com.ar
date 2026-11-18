<?php

namespace App\Http\Controllers;

use App\Models\OrderSheet;
use App\Services\OrderSheetPrintSheet;
use App\Services\OrderSheetWeekService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Impresión de una planilla de pedidos para una semana (query ?start=): vista
 * para imprimir desde el navegador y PDF descargable, en A4 apaisada.
 */
class OrderSheetPrintController extends Controller
{
    public function __construct(
        private readonly OrderSheetPrintSheet $printSheet,
        private readonly OrderSheetWeekService $weeks,
    ) {}

    public function show(Request $request, OrderSheet $orderSheet): View
    {
        $this->authorize('view', $orderSheet);

        $start = $this->start($request);

        return view('pedidos.print.screen', [
            'orderSheet' => $orderSheet,
            'start' => $start,
            'sheet' => $this->printSheet->for($orderSheet, $start),
        ]);
    }

    public function pdf(Request $request, OrderSheet $orderSheet): Response
    {
        $this->authorize('view', $orderSheet);

        $start = $this->start($request);

        return Pdf::loadView('pedidos.print.pdf', ['sheet' => $this->printSheet->for($orderSheet, $start)])
            ->setPaper('a4', 'landscape')
            ->download('pedidos-'.Str::slug($orderSheet->name).'-'.$start->format('Y-m-d').'.pdf');
    }

    private function start(Request $request): CarbonImmutable
    {
        return $this->weeks->mondayFromInput($request->string('start')->toString());
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\DoughPlanner;
use App\Services\ReportLetterhead;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

/**
 * Producción › Amasado: la planilla del día (qué amasar y cuánta masa
 * preparar), en pantalla, para imprimir y en PDF. Solo lee: la ven todos los
 * roles que ven Pedidos.
 */
class DoughPlanController extends Controller
{
    public function __construct(
        private readonly DoughPlanner $planner,
        private readonly ReportLetterhead $letterhead,
    ) {}

    public function index(Request $request): View
    {
        $tenant = app(Tenant::class);
        $date = $this->date($request);

        return view('produccion.amasado.index', [
            'date' => $date,
            'plan' => $this->planner->forDate($tenant->defaultLocation(), $date),
            ...$this->extraIndexData($tenant, $date),
        ]);
    }

    public function print(Request $request): View
    {
        $date = $this->date($request);

        return view('produccion.amasado.print.screen', [
            'date' => $date,
            'sheet' => $this->sheet($date),
        ]);
    }

    public function pdf(Request $request): Response
    {
        $date = $this->date($request);

        return Pdf::loadView('produccion.amasado.print.pdf', ['sheet' => $this->sheet($date)])
            ->setPaper('a4', 'portrait')
            ->download('amasado-'.$date->format('Y-m-d').'.pdf');
    }

    /**
     * Datos de la pantalla que no son la planilla en sí (amasados del día,
     * último cierre de cámara). Vacío hasta que existan esos módulos.
     *
     * @return array<string, mixed>
     */
    private function extraIndexData(Tenant $tenant, CarbonImmutable $date): array
    {
        return [];
    }

    /**
     * @return array{business: array<string, mixed>, generated_at: string, plan: array<string, mixed>}
     */
    private function sheet(CarbonImmutable $date): array
    {
        $tenant = app(Tenant::class);

        return [
            'business' => $this->letterhead->for($tenant),
            'generated_at' => CarbonImmutable::now()->format('d/m/Y H:i'),
            'plan' => $this->planner->forDate($tenant->defaultLocation(), $date),
        ];
    }

    /** El día pedido por query (?fecha=), o hoy si no viene uno válido. */
    private function date(Request $request): CarbonImmutable
    {
        $input = $request->string('fecha')->toString();

        try {
            return ($input !== '' ? CarbonImmutable::parse($input) : CarbonImmutable::today())->startOfDay();
        } catch (Throwable) {
            return CarbonImmutable::today();
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPerson;
use App\Models\Tenant;
use App\Services\DeliveryRouteSheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Hoja diaria del repartidor (query ?date=&delivery_person=): vista para
 * imprimir desde el navegador y PDF descargable, en A4 apaisada.
 * `delivery_person` vacío = clientes sin repartidor.
 */
class DeliveryRouteSheetController extends Controller
{
    public function __construct(private readonly DeliveryRouteSheet $routeSheet) {}

    public function show(Request $request): View
    {
        [$date, $deliveryPerson] = $this->resolve($request);

        return view('reparto.print.screen', [
            'date' => $date,
            'sheet' => $this->routeSheet->for(app(Tenant::class), $date, $deliveryPerson),
        ]);
    }

    public function pdf(Request $request): Response
    {
        [$date, $deliveryPerson] = $this->resolve($request);

        return Pdf::loadView('reparto.print.pdf', [
            'sheet' => $this->routeSheet->for(app(Tenant::class), $date, $deliveryPerson),
        ])
            ->setPaper('a4', 'landscape')
            ->download('reparto-'.Str::slug($deliveryPerson === null ? 'sin-repartidor' : $deliveryPerson->name).'-'.$date->format('Y-m-d').'.pdf');
    }

    /**
     * @return array{0: CarbonImmutable, 1: DeliveryPerson|null}
     */
    private function resolve(Request $request): array
    {
        $input = $request->string('date')->toString();

        try {
            $date = ($input !== '' ? CarbonImmutable::parse($input) : CarbonImmutable::now())->startOfDay();
        } catch (Throwable) {
            $date = CarbonImmutable::now()->startOfDay();
        }

        $deliveryPerson = $request->filled('delivery_person')
            ? app(Tenant::class)->deliveryPeople()->findOrFail($request->integer('delivery_person'))
            : null;

        return [$date, $deliveryPerson];
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveOrderSheetWeekRequest;
use App\Models\OrderSheet;
use App\Services\OrderSheetWeekService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Contenido de una semana de una planilla de pedidos: guardar el set completo
 * de filas y volver a copiar la semana anterior.
 */
class OrderSheetWeekController extends Controller
{
    public function __construct(private readonly OrderSheetWeekService $weeks) {}

    public function update(SaveOrderSheetWeekRequest $request, OrderSheet $orderSheet): RedirectResponse
    {
        $this->authorize('update', $orderSheet);

        $week = $this->weeks->ensureWeek($orderSheet, CarbonImmutable::parse($request->validated('start')));

        /** @var array<int, array{product_id: int, quantities: array<int, float|int|string|null>}> $lines */
        $lines = $request->validated('lines');

        $this->weeks->save($week, $lines);

        return $this->backToWeek($orderSheet, $week->starts_on->toDateString(), 'Semana guardada.');
    }

    public function recopy(Request $request, OrderSheet $orderSheet): RedirectResponse
    {
        $this->authorize('update', $orderSheet);

        $validated = $request->validate(['start' => ['required', 'date']]);

        $week = $this->weeks->ensureWeek($orderSheet, CarbonImmutable::parse($validated['start']));
        $this->weeks->recopy($week);

        return $this->backToWeek($orderSheet, $week->starts_on->toDateString(), 'Se copió la semana anterior.');
    }

    private function backToWeek(OrderSheet $orderSheet, string $start, string $status): RedirectResponse
    {
        return redirect()
            ->route('pedidos.index', ['sheet' => $orderSheet->id, 'start' => $start])
            ->with('status', $status);
    }
}

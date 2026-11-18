<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderSheetRequest;
use App\Http\Requests\UpdateOrderSheetRequest;
use App\Models\OrderSheet;
use App\Models\OrderSheetLine;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\OrderSheetWeekService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Pedidos: las planillas semanales (lunes a domingo) que arma cada negocio,
 * una por tipo de pedido, con los artículos y cantidades a elaborar por día.
 * Este controller muestra la pantalla y administra las planillas; el contenido
 * de cada semana se guarda en OrderSheetWeekController.
 */
class OrderSheetController extends Controller
{
    public function __construct(
        private readonly OrderSheetWeekService $weeks,
        private readonly AdminActivityRecorder $recorder,
    ) {}

    public function index(Request $request): View
    {
        $tenant = app(Tenant::class);

        $allSheets = $tenant->orderSheets()->ordered()->get();
        $sheets = $allSheets->where('active', true)->values();
        $sheet = $sheets->firstWhere('id', $request->integer('sheet')) ?? $sheets->first();

        $start = $this->weekStart($request->string('start')->toString());
        $week = $sheet !== null ? $this->weeks->weekFor($sheet, $start) : null;

        $lines = $week !== null
            ? $week->lines->map(fn (OrderSheetLine $line) => [
                'product_id' => $line->product_id,
                'name' => $line->product->name,
                'unit' => $line->product->unit->short(),
                'q' => $line->quantities(),
            ])->values()->all()
            : [];

        $products = Product::query()->producible()->orderBy('name')->get(['id', 'name', 'unit'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit->short(),
            ])->values()->all();

        return view('pedidos.index', [
            'allSheets' => $allSheets,
            'sheets' => $sheets,
            'sheet' => $sheet,
            'start' => $start,
            'days' => collect(range(0, 6))->map(fn (int $offset) => $start->addDays($offset)),
            'lines' => $lines,
            'products' => $products,
            'hasWeek' => $week !== null,
            'canEdit' => Gate::allows('manage-costs'),
        ]);
    }

    public function store(StoreOrderSheetRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);

        $sheet = $tenant->orderSheets()->create([
            'name' => $request->validated('name'),
            'position' => ((int) $tenant->orderSheets()->max('position')) + 1,
        ]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'order_sheet',
            targetId: $sheet->id,
            action: 'order_sheet.created',
            payload: ['name' => $sheet->name],
            tenantId: $tenant->id,
        );

        return redirect()->route('pedidos.index', ['sheet' => $sheet->id])->with('status', 'Planilla creada.');
    }

    public function update(UpdateOrderSheetRequest $request, OrderSheet $orderSheet): RedirectResponse
    {
        $this->authorize('update', $orderSheet);

        $orderSheet->update($request->validated());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'order_sheet',
            targetId: $orderSheet->id,
            action: 'order_sheet.updated',
            payload: ['name' => $orderSheet->name],
            tenantId: $orderSheet->tenant_id,
        );

        return back(fallback: route('pedidos.index'))->with('status', 'Planilla actualizada.');
    }

    public function toggleActive(Request $request, OrderSheet $orderSheet): RedirectResponse
    {
        $this->authorize('update', $orderSheet);

        $orderSheet->update(['active' => ! $orderSheet->active]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'order_sheet',
            targetId: $orderSheet->id,
            action: $orderSheet->active ? 'order_sheet.activated' : 'order_sheet.deactivated',
            payload: ['name' => $orderSheet->name],
            tenantId: $orderSheet->tenant_id,
        );

        return back(fallback: route('pedidos.index'))->with('status', $orderSheet->active ? 'Planilla activada.' : 'Planilla pausada.');
    }

    /**
     * Solo se borra una planilla sin artículos cargados en ninguna semana;
     * si ya tiene historia se pausa, para no perder lo planificado.
     */
    public function destroy(Request $request, OrderSheet $orderSheet): RedirectResponse
    {
        $this->authorize('delete', $orderSheet);

        if ($orderSheet->weeks()->whereHas('lines')->exists()) {
            return back(fallback: route('pedidos.index'))
                ->withErrors(['order_sheet' => 'La planilla tiene semanas cargadas. Pausala en vez de borrarla.']);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'order_sheet',
            targetId: $orderSheet->id,
            action: 'order_sheet.deleted',
            payload: ['name' => $orderSheet->name],
            tenantId: $orderSheet->tenant_id,
        );

        $orderSheet->delete();

        return redirect()->route('pedidos.index')->with('status', 'Planilla borrada.');
    }

    /** El lunes de la semana pedida (o de la actual si no viene una fecha válida). */
    private function weekStart(string $date): CarbonImmutable
    {
        try {
            $base = $date !== '' ? CarbonImmutable::parse($date) : CarbonImmutable::now();
        } catch (Throwable) {
            $base = CarbonImmutable::now();
        }

        return $this->weeks->mondayOf($base);
    }
}

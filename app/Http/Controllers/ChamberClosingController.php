<?php

namespace App\Http\Controllers;

use App\Enums\WasteReason;
use App\Http\Requests\SaveChamberClosingRequest;
use App\Models\ChamberClosing;
use App\Models\ChamberClosingLine;
use App\Models\Tenant;
use App\Services\ChamberClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Producción › Cierre de cámara: el hornero cuenta lo que quedó y lo que
 * falta pasa a terminado. Abrir, contar, confirmar y anular es para owner y
 * admin (middleware de rol); ver, para todos los roles con negocio.
 */
class ChamberClosingController extends Controller
{
    public function __construct(private readonly ChamberClosingService $closings) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);

        return view('produccion.camara.index', [
            'open' => $this->closings->openFor($tenant),
            'closings' => ChamberClosing::query()
                ->where('tenant_id', $tenant->id)
                ->withCount('lines')
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $closing = $this->closings->open(app(Tenant::class));

        return redirect()->route('produccion.camara.show', $closing);
    }

    public function show(ChamberClosing $chamberClosing): View
    {
        $this->authorize('view', $chamberClosing);

        $chamberClosing->load(['lines.product.recipe', 'confirmedBy', 'cancelledBy']);

        if ($chamberClosing->isDraft()) {
            return view('produccion.camara.edit', [
                'closing' => $chamberClosing,
                'lines' => $chamberClosing->lines->map(fn (ChamberClosingLine $line) => $this->linePayload($line))->values()->all(),
                'wasteReasons' => array_map(fn (WasteReason $reason) => [
                    'value' => $reason->value,
                    'label' => $reason->label(),
                    'note' => $reason->requiresNote(),
                ], WasteReason::forStage(WasteReason::STAGE_OVEN)),
            ]);
        }

        return view('produccion.camara.show', [
            'closing' => $chamberClosing,
            'movements' => $chamberClosing->stockMovements()->with(['ingredient', 'packaging', 'product', 'location'])->orderBy('id')->get(),
        ]);
    }

    /** Guarda lo contado y, si viene `confirm`, confirma el cierre. */
    public function update(SaveChamberClosingRequest $request, ChamberClosing $chamberClosing): RedirectResponse
    {
        $this->authorize('update', $chamberClosing);

        $chamberClosing->load('lines.product.recipe');
        $perContainer = $chamberClosing->lines->mapWithKeys(fn (ChamberClosingLine $line) => [$line->product_id => $line->product->unitsPerContainer()]);

        $lines = array_map(function (array $line) use ($perContainer) {
            $per = $perContainer[(int) $line['product_id']] ?? null;
            $counted = (float) ($line['counted'] ?? 0);

            return $line + ['counted_units' => $per !== null ? $counted * $per : $counted];
        }, $request->validated('lines'));

        $this->closings->saveDraft($chamberClosing, $lines);

        if (! $request->boolean('confirm')) {
            return redirect()->route('produccion.camara.show', $chamberClosing)->with('status', 'Conteo guardado.');
        }

        $this->closings->confirm($chamberClosing->fresh() ?? $chamberClosing, $request->user());

        return redirect()->route('produccion.camara.show', $chamberClosing)
            ->with('status', "Cierre de cámara #{$chamberClosing->number} confirmado: lo horneado pasó a terminado.");
    }

    public function cancel(Request $request, ChamberClosing $chamberClosing): RedirectResponse
    {
        $this->authorize('update', $chamberClosing);

        $this->closings->cancel($chamberClosing, $request->user());

        return redirect()->route('produccion.camara.show', $chamberClosing)->with('status', "Cierre #{$chamberClosing->number} anulado.");
    }

    public function destroy(ChamberClosing $chamberClosing): RedirectResponse
    {
        $this->authorize('update', $chamberClosing);

        $this->closings->deleteDraft($chamberClosing);

        return redirect()->route('produccion.camara.index')->with('status', 'Cierre descartado.');
    }

    /**
     * @return array{product_id: int, name: string, unit: string, container: array{type: string, label: string, short: string, per: float}|null, expected: float, counted: float, waste: float, reason: string, note: string}
     */
    private function linePayload(ChamberClosingLine $line): array
    {
        $product = $line->product;
        $container = $product->containerPayload();

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit->short(),
            'container' => $container,
            'expected' => $line->expected_units,
            'counted' => $container !== null && $container['per'] > 0
                ? round($line->counted_units / $container['per'], 3)
                : $line->counted_units,
            'waste' => $line->waste_units,
            'reason' => $line->waste_reason->value ?? '',
            'note' => (string) $line->waste_note,
        ];
    }
}

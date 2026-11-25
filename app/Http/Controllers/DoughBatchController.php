<?php

namespace App\Http\Controllers;

use App\Enums\WasteReason;
use App\Http\Requests\SaveDoughBatchRequest;
use App\Models\DoughBatch;
use App\Models\DoughBatchLine;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\DoughBatchService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Producción › Amasado: registrar lo que realmente se amasó. Generar,
 * editar, confirmar y anular es para owner y admin (middleware de rol);
 * ver un amasado, para todos los roles con negocio.
 */
class DoughBatchController extends Controller
{
    public function __construct(private readonly DoughBatchService $batches) {}

    /** Genera el borrador del día desde la planilla, o uno vacío (`manual`). */
    public function store(Request $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $date = $this->date($request->string('fecha')->toString());

        $batch = $request->boolean('manual')
            ? $this->batches->createAdHoc($tenant, $date)
            : $this->batches->createFromPlan($tenant, $date);

        return redirect()->route('produccion.amasados.show', $batch);
    }

    public function show(DoughBatch $doughBatch): View
    {
        $this->authorize('view', $doughBatch);

        $doughBatch->load(['lines.product.recipe', 'masses.recipe', 'confirmedBy', 'cancelledBy']);

        if ($doughBatch->isDraft()) {
            return view('produccion.amasados.edit', [
                'batch' => $doughBatch,
                'lines' => $doughBatch->lines->map(fn (DoughBatchLine $line) => $this->linePayload($line))->values()->all(),
                'productOptions' => $this->productOptions(),
                'wasteReasons' => $this->wasteReasons(),
            ]);
        }

        $movements = $doughBatch->stockMovements()
            ->with(['ingredient', 'packaging', 'product', 'location'])
            ->orderBy('id')
            ->get();

        return view('produccion.amasados.show', ['batch' => $doughBatch, 'movements' => $movements]);
    }

    /** Guarda el borrador y, si viene `confirm`, lo confirma. */
    public function update(SaveDoughBatchRequest $request, DoughBatch $doughBatch): RedirectResponse
    {
        $this->authorize('update', $doughBatch);

        $this->batches->saveDraft($doughBatch, $request->validated());

        if (! $request->boolean('confirm')) {
            return redirect()->route('produccion.amasados.show', $doughBatch)->with('status', 'Borrador guardado.');
        }

        $this->batches->confirm($doughBatch->fresh() ?? $doughBatch, $request->user());

        return redirect()->route('produccion.amasados.show', $doughBatch)
            ->with('status', "Amasado #{$doughBatch->number} confirmado: se descontaron los insumos y entró lo amasado.");
    }

    public function cancel(Request $request, DoughBatch $doughBatch): RedirectResponse
    {
        $this->authorize('update', $doughBatch);

        $negative = $this->batches->cancel($doughBatch, $request->user());

        $redirect = redirect()->route('produccion.amasados.show', $doughBatch)->with('status', "Amasado #{$doughBatch->number} anulado.");

        return $negative === []
            ? $redirect
            : $redirect->with('error', 'La cámara quedó en negativo para '.implode(', ', $negative).': ese crudo ya se había horneado.');
    }

    public function destroy(DoughBatch $doughBatch): RedirectResponse
    {
        $this->authorize('update', $doughBatch);

        $date = $doughBatch->dough_date->toDateString();
        $this->batches->deleteDraft($doughBatch);

        return redirect()->route('produccion.amasado.index', ['fecha' => $date])->with('status', 'Borrador descartado.');
    }

    /**
     * @return array{product_id: int, name: string, unit: string, goes_to_chamber: bool, container: array{type: string, label: string, short: string, per: float}|null, planned: float, actual: float, waste: float, reason: string, note: string}
     */
    private function linePayload(DoughBatchLine $line): array
    {
        $product = $line->product;

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit->short(),
            'goes_to_chamber' => $product->usesProofing(),
            'container' => $product->containerPayload(),
            'planned' => $line->planned_units,
            'actual' => $line->actual_units,
            'waste' => $line->waste_units,
            'reason' => $line->waste_reason->value ?? '',
            'note' => (string) $line->waste_note,
        ];
    }

    /**
     * Artículos que se pueden sumar a mano al borrador.
     *
     * @return list<array{product_id: int, name: string, unit: string, goes_to_chamber: bool, container: array{type: string, label: string, short: string, per: float}|null}>
     */
    private function productOptions(): array
    {
        return Product::query()
            ->producible()
            ->with('recipe')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit->short(),
                'goes_to_chamber' => $product->usesProofing(),
                'container' => $product->containerPayload(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{value: string, label: string, note: bool}> */
    private function wasteReasons(): array
    {
        return array_map(fn (WasteReason $reason) => [
            'value' => $reason->value,
            'label' => $reason->label(),
            'note' => $reason->requiresNote(),
        ], WasteReason::forStage(WasteReason::STAGE_DOUGH));
    }

    private function date(string $input): CarbonImmutable
    {
        try {
            return ($input !== '' ? CarbonImmutable::parse($input) : CarbonImmutable::today())->startOfDay();
        } catch (Throwable) {
            return CarbonImmutable::today();
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Services\ProductionOrderSheets;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProductionOrderSheetController extends Controller
{
    public function __construct(private readonly ProductionOrderSheets $sheets) {}

    public function production(ProductionOrder $productionOrder): View
    {
        $this->authorize('view', $productionOrder);

        $sheet = $this->sheets->production($productionOrder);

        return view('production-orders.sheets.production.screen', compact('productionOrder', 'sheet'));
    }

    public function productionPdf(ProductionOrder $productionOrder): Response
    {
        $this->authorize('view', $productionOrder);

        $sheet = $this->sheets->production($productionOrder);

        return Pdf::loadView('production-orders.sheets.production.pdf', compact('sheet'))
            ->setPaper('a4')
            ->download("produccion-orden-{$productionOrder->number}.pdf");
    }

    public function delivery(Request $request, ProductionOrder $productionOrder): View
    {
        $this->authorize('view', $productionOrder);

        $sheet = $this->sheets->delivery($productionOrder);
        $group = $request->string('group')->toString() ?: null;
        $selected = $this->filterGroup($sheet, $group);

        return view('production-orders.sheets.delivery.screen', [
            'productionOrder' => $productionOrder,
            'sheet' => $sheet,
            'document' => [...$sheet, 'groups' => $selected],
            'group' => $group,
        ]);
    }

    public function deliveryPdf(Request $request, ProductionOrder $productionOrder): Response
    {
        $this->authorize('view', $productionOrder);

        $sheet = $this->sheets->delivery($productionOrder);
        $group = $request->string('group')->toString() ?: null;
        $document = [...$sheet, 'groups' => $this->filterGroup($sheet, $group)];

        $filename = $group
            ? "reparto-orden-{$productionOrder->number}-{$group}.pdf"
            : "reparto-orden-{$productionOrder->number}.pdf";

        return Pdf::loadView('production-orders.sheets.delivery.pdf', ['sheet' => $document])
            ->setPaper('a4')
            ->download($filename);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filterGroup(array $sheet, ?string $group): array
    {
        if ($group === null) {
            return $sheet['groups'];
        }

        $filtered = array_values(array_filter($sheet['groups'], fn (array $entry) => $entry['key'] === $group));

        abort_if($filtered === [], 404);

        return $filtered;
    }
}

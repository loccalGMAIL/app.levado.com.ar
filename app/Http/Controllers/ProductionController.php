<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Services\AdminActivityRecorder;
use App\Services\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductionController extends Controller
{
    public function __construct(
        private readonly ProductionService $productions,
        private readonly AdminActivityRecorder $recorder,
    ) {}

    public function show(Production $production): View
    {
        $this->authorize('view', $production);

        $production->load(['product', 'recipe', 'user', 'productionOrder']);
        $movements = $production->movements()
            ->with(['ingredient', 'packaging', 'product'])
            ->orderBy('id')
            ->get();

        return view('production.show', compact('production', 'movements'));
    }

    public function cancel(Production $production): RedirectResponse
    {
        $this->authorize('update', $production);

        $this->productions->cancel($production, request()->user());

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'production',
            targetId: $production->id,
            action: 'production.cancelled',
            payload: ['product' => $production->product?->name],
            tenantId: $production->tenant_id,
        );

        return back(fallback: route('products.history'))->with('status', 'Producción anulada.');
    }
}

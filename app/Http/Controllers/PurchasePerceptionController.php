<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchasePerceptionRequest;
use App\Models\Purchase;
use App\Models\PurchasePerception;
use App\Services\AdminActivityRecorder;
use App\Services\PurchasePerceptionSync;
use Illuminate\Http\RedirectResponse;

class PurchasePerceptionController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly PurchasePerceptionSync $sync,
    ) {}

    public function store(StorePurchasePerceptionRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $perception = $this->sync->store($purchase, $request->validated());

        $this->audit($purchase, $perception, 'purchase_perception.created');

        return back()->with('status', 'Percepción agregada.');
    }

    public function update(StorePurchasePerceptionRequest $request, Purchase $purchase, PurchasePerception $perception): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $this->sync->update($perception, $request->validated());

        $this->audit($purchase, $perception, 'purchase_perception.updated');

        return back()->with('status', 'Percepción actualizada.');
    }

    public function destroy(Purchase $purchase, PurchasePerception $perception): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $this->audit($purchase, $perception, 'purchase_perception.deleted');

        $perception->delete();

        return back()->with('status', 'Percepción eliminada.');
    }

    private function audit(Purchase $purchase, PurchasePerception $perception, string $action): void
    {
        $this->recorder->record(
            actor: request()->user(),
            targetType: 'purchase_perception',
            targetId: $perception->id,
            action: $action,
            payload: ['description' => $perception->description, 'amount' => (float) $perception->amount],
            tenantId: $purchase->tenant_id,
        );
    }
}

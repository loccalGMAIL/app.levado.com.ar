<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseDiscountRequest;
use App\Models\Purchase;
use App\Models\PurchaseDiscount;
use App\Services\AdminActivityRecorder;
use App\Services\PurchaseDiscountSync;
use App\Services\PurchasePerceptionSync;
use Illuminate\Http\RedirectResponse;

class PurchaseDiscountController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly PurchaseDiscountSync $sync,
        private readonly PurchasePerceptionSync $perceptionSync,
    ) {}

    public function store(StorePurchaseDiscountRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $discount = $this->sync->store($purchase, $request->validated());
        $this->perceptionSync->refresh($purchase);

        $this->audit($purchase, $discount, 'purchase_discount.created');

        return back()->with('status', 'Descuento agregado.');
    }

    public function update(StorePurchaseDiscountRequest $request, Purchase $purchase, PurchaseDiscount $discount): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $this->sync->update($discount, $request->validated());
        $this->perceptionSync->refresh($purchase);

        $this->audit($purchase, $discount, 'purchase_discount.updated');

        return back()->with('status', 'Descuento actualizado.');
    }

    public function destroy(Purchase $purchase, PurchaseDiscount $discount): RedirectResponse
    {
        $this->authorize('update', $purchase);

        $this->audit($purchase, $discount, 'purchase_discount.deleted');

        $discount->delete();
        $this->perceptionSync->refresh($purchase);

        return back()->with('status', 'Descuento eliminado.');
    }

    private function audit(Purchase $purchase, PurchaseDiscount $discount, string $action): void
    {
        $this->recorder->record(
            actor: request()->user(),
            targetType: 'purchase_discount',
            targetId: $discount->id,
            action: $action,
            payload: ['description' => $discount->description, 'amount' => (float) $discount->amount],
            tenantId: $purchase->tenant_id,
        );
    }
}

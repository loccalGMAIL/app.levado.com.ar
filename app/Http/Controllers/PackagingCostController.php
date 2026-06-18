<?php

namespace App\Http\Controllers;

use App\Models\Packaging;
use App\Services\AdminActivityRecorder;
use App\Services\RecipeCostPropagator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackagingCostController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly RecipeCostPropagator $propagator,
    ) {}

    public function update(Request $request, Packaging $packaging): JsonResponse
    {
        $this->authorize('update', $packaging);

        $validated = $request->validate([
            'cost_per_unit' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $newCost = (float) $validated['cost_per_unit'];
        $costChanged = (float) $packaging->cost_per_unit !== $newCost;

        if ($costChanged) {
            $packaging->priceLogs()->create([
                'cost_per_unit' => $newCost,
                'recorded_at' => now(),
            ]);
        }

        $packaging->update(['cost_per_unit' => $newCost]);

        if ($costChanged) {
            $this->propagator->propagateFromPackaging($packaging->id);
        }

        if ($costChanged) {
            $this->recorder->record(
                actor: $request->user(),
                targetType: 'packaging',
                targetId: $packaging->id,
                action: 'packaging.updated',
                payload: ['name' => $packaging->name],
                tenantId: $packaging->tenant_id,
            );
        }

        return response()->json([
            'cost_per_unit' => (float) $packaging->cost_per_unit,
            'cost_per_unit_formatted' => number_format((float) $packaging->cost_per_unit, 2, ',', '.'),
        ]);
    }
}

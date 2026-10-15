<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePerceptionConceptRequest;
use App\Http\Requests\UpdatePerceptionConceptRequest;
use App\Models\PerceptionConcept;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PerceptionConceptController extends Controller
{
    public function __construct(private readonly AdminActivityRecorder $recorder) {}

    public function store(StorePerceptionConceptRequest $request): RedirectResponse|JsonResponse
    {
        $tenant = app(Tenant::class);
        $concept = $tenant->perceptionConcepts()->create($request->conceptData());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'perception_concept',
            targetId: $concept->id,
            action: 'perception_concept.created',
            payload: ['name' => $concept->name],
            tenantId: $tenant->id,
        );

        if ($request->wantsJson()) {
            return response()->json($this->toArray($concept), 201);
        }

        return back(fallback: route('purchases.index'))
            ->with('reopen_perception_concepts', true)
            ->with('status', 'Concepto creado.');
    }

    public function update(UpdatePerceptionConceptRequest $request, PerceptionConcept $perceptionConcept): RedirectResponse
    {
        $this->authorizeConcept($perceptionConcept);

        $perceptionConcept->update($request->conceptData());

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'perception_concept',
            targetId: $perceptionConcept->id,
            action: 'perception_concept.updated',
            payload: ['name' => $perceptionConcept->name],
            tenantId: $perceptionConcept->tenant_id,
        );

        return back(fallback: route('purchases.index'))
            ->with('reopen_perception_concepts', true)
            ->with('status', 'Concepto actualizado.');
    }

    /**
     * Las percepciones ya cargadas conservan su snapshot: borrar el concepto sólo
     * las desvincula (nullOnDelete), no las modifica.
     */
    public function destroy(PerceptionConcept $perceptionConcept): RedirectResponse
    {
        $this->authorizeConcept($perceptionConcept);

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'perception_concept',
            targetId: $perceptionConcept->id,
            action: 'perception_concept.deleted',
            payload: ['name' => $perceptionConcept->name],
            tenantId: $perceptionConcept->tenant_id,
        );

        $perceptionConcept->delete();

        return back(fallback: route('purchases.index'))
            ->with('reopen_perception_concepts', true)
            ->with('status', 'Concepto eliminado.');
    }

    /**
     * @return array{id: int, name: string, type: string, jurisdiction: ?string, default_rate: ?float}
     */
    private function toArray(PerceptionConcept $concept): array
    {
        return [
            'id' => $concept->id,
            'name' => $concept->name,
            'type' => $concept->type->value,
            'jurisdiction' => $concept->jurisdiction?->value,
            'default_rate' => $concept->default_rate !== null ? (float) $concept->default_rate : null,
        ];
    }

    private function authorizeConcept(PerceptionConcept $concept): void
    {
        abort_unless($concept->tenant_id === app(Tenant::class)->id, 403);
    }
}

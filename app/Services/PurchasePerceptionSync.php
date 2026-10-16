<?php

namespace App\Services;

use App\Enums\PerceptionBase;
use App\Models\PerceptionConcept;
use App\Models\Purchase;
use App\Models\PurchasePerception;

/**
 * Alta, edición y recálculo de las percepciones de una compra.
 *
 * El importe se persiste siempre (los totales salen por SQL). Una percepción
 * automática se recalcula como base × alícuota cuando cambian los renglones;
 * una manual (importe escrito a mano, o leído de la factura) nunca se pisa.
 */
class PurchasePerceptionSync
{
    /**
     * @param  array{perception_concept_id?: int|string|null, type?: string, jurisdiction?: ?string, description?: ?string, base_amount?: float|string|null, rate?: float|string|null, amount?: float|string|null}  $data
     */
    public function store(Purchase $purchase, array $data): PurchasePerception
    {
        $perception = $purchase->perceptions()->make($this->attributes($purchase, $data));
        $perception->save();

        return $perception;
    }

    /**
     * @param  array{perception_concept_id?: int|string|null, type?: string, jurisdiction?: ?string, description?: ?string, base_amount?: float|string|null, rate?: float|string|null, amount?: float|string|null}  $data
     */
    public function update(PurchasePerception $perception, array $data): PurchasePerception
    {
        $perception->update($this->attributes($perception->purchase, $data));

        return $perception;
    }

    /**
     * Recalcula las percepciones no manuales contra la base actual de cada una.
     * Se llama cada vez que cambian los renglones de la compra.
     */
    public function refresh(Purchase $purchase): void
    {
        $purchase->perceptions()
            ->where('amount_is_manual', false)
            ->get()
            ->each(function (PurchasePerception $perception) use ($purchase) {
                $perception->setRelation('purchase', $purchase);
                $perception->update(['amount' => $this->computeAmount($purchase, $perception->base_type, $perception->base_amount, $perception->rate)]);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Purchase $purchase, array $data): array
    {
        $concept = $this->resolveConcept($purchase, $data['perception_concept_id'] ?? null);

        $base = $this->nullableNumber($data['base_amount'] ?? null);
        $rate = $this->nullableNumber($data['rate'] ?? $concept?->default_rate);
        $typedAmount = $this->nullableNumber($data['amount'] ?? null);

        // Lo que elige el usuario en el formulario manda; el concepto sólo propone el default.
        $baseType = PerceptionBase::tryFrom((string) ($data['base_type'] ?? '')) ?? $concept->base_type ?? PerceptionBase::NetTaxed;

        $isManual = $typedAmount !== null;

        return [
            'perception_concept_id' => $concept?->id,
            'type' => $concept->type->value ?? $data['type'] ?? 'otro',
            'jurisdiction' => $concept !== null ? $concept->jurisdiction?->value : ($data['jurisdiction'] ?? null),
            'description' => $data['description'] ?? $concept->name ?? 'Percepción',
            'base_type' => $baseType->value,
            'base_amount' => $base,
            'rate' => $rate,
            'amount' => $isManual ? round($typedAmount, 2) : $this->computeAmount($purchase, $baseType, $base, $rate),
            'amount_is_manual' => $isManual,
        ];
    }

    private function computeAmount(Purchase $purchase, PerceptionBase $baseType, float|string|null $base, float|string|null $rate): float
    {
        if ($rate === null || $rate === '') {
            return 0.0;
        }

        $effectiveBase = $base !== null ? (float) $base : $purchase->baseFor($baseType);

        return round($effectiveBase * (float) $rate / 100, 2);
    }

    private function resolveConcept(Purchase $purchase, int|string|null $conceptId): ?PerceptionConcept
    {
        if ($conceptId === null || $conceptId === '') {
            return null;
        }

        return PerceptionConcept::query()
            ->where('tenant_id', $purchase->tenant_id)
            ->find($conceptId);
    }

    private function nullableNumber(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : (float) $value;
    }
}

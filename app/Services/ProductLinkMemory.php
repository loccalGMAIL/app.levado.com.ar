<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Models\PurchaseLine;
use App\Models\SupplierProductLink;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Recuerda qué ítem del catálogo significa cada texto de renglón para un
 * proveedor dado, para que la vinculación hecha a mano se aproveche en las
 * facturas siguientes en vez de volver a depender de lo que adivine la IA.
 *
 * Sólo aprende de decisiones humanas (la pantalla de vinculación), y lo que
 * devuelve es siempre una pre-selección: el renglón queda pendiente y ningún
 * costo ni stock se mueve sin que el usuario lo confirme.
 */
class ProductLinkMemory
{
    /**
     * El vínculo recordado para este texto, o null si no hay ninguno utilizable.
     *
     * @return array{purchaseable_type: string, purchaseable_id: int, pkg_qty: ?float}|null
     */
    public function recall(Tenant $tenant, ?int $supplierId, ?string $rawName): ?array
    {
        if ($supplierId === null || blank($rawName)) {
            return null;
        }

        return $this->recallMany($tenant, $supplierId, [$rawName])[$this->fold($rawName)] ?? null;
    }

    /**
     * Resuelve todos los renglones de una factura de una sola vez: una factura
     * con 30 líneas no puede costar 30 queries.
     *
     * @param  array<int, ?string>  $rawNames
     * @return array<string, array{purchaseable_type: string, purchaseable_id: int, pkg_qty: ?float}>
     *                                                                                                indexado por texto plegado
     */
    public function recallMany(Tenant $tenant, ?int $supplierId, array $rawNames): array
    {
        $keys = collect($rawNames)
            ->filter(fn (?string $name) => filled($name))
            ->map(fn (string $name) => $this->fold($name))
            ->filter(fn (string $key) => $key !== '')
            ->unique()
            ->values();

        if ($supplierId === null || $keys->isEmpty()) {
            return [];
        }

        $links = SupplierProductLink::query()
            ->where('tenant_id', $tenant->id)
            ->where('supplier_id', $supplierId)
            ->whereIn('raw_name_normalized', $keys->all())
            ->get();

        if ($links->isEmpty()) {
            return [];
        }

        $owned = $this->ownedIds($tenant, $links);

        return $links
            // purchaseable_id no tiene FK: el ítem pudo borrarse, o el vínculo
            // pudo quedar apuntando fuera del tenant. Un id inválido metido en
            // los hidden inputs del form daría un match fantasma sin nombre.
            ->filter(fn (SupplierProductLink $link) => in_array(
                $link->purchaseable_id,
                $owned[$link->purchaseable_type] ?? [],
                true,
            ))
            ->mapWithKeys(fn (SupplierProductLink $link) => [
                $link->raw_name_normalized => [
                    'purchaseable_type' => $link->purchaseable_type,
                    'purchaseable_id' => (int) $link->purchaseable_id,
                    'pkg_qty' => $link->pkg_qty !== null ? (float) $link->pkg_qty : null,
                ],
            ])
            ->all();
    }

    /**
     * Guarda (o refresca) la decisión humana sobre este renglón.
     *
     * @param  ?float  $pkgQty  divisor en unidades del catálogo, cuando las
     *                          unidades de compra y catálogo son incompatibles
     */
    public function remember(PurchaseLine $line, ?float $pkgQty = null): void
    {
        $key = $this->keyFor($line);

        if ($key === null || ! $line->isMatched()) {
            return;
        }

        [$supplierId, $normalized] = $key;

        $link = SupplierProductLink::query()
            ->where('tenant_id', $line->purchase->tenant_id)
            ->where('supplier_id', $supplierId)
            ->where('raw_name_normalized', $normalized)
            ->first();

        // Un divisor viejo se conserva mientras el vínculo apunte al mismo ítem:
        // la confirmación en masa no manda divisor, y sin esto el primer clic en
        // "Aplicar N sugerencias" borraría el que se cargó a mano.
        $sameItem = $link !== null
            && $link->purchaseable_type === $line->purchaseable_type
            && (int) $link->purchaseable_id === (int) $line->purchaseable_id;

        $resolvedPkgQty = match (true) {
            $pkgQty !== null && $pkgQty > 0 => $pkgQty,
            $sameItem => $link->pkg_qty !== null ? (float) $link->pkg_qty : null,
            default => null,
        };

        $attributes = [
            'raw_name_sample' => (string) $line->raw_name,
            'purchaseable_type' => $line->purchaseable_type,
            'purchaseable_id' => $line->purchaseable_id,
            'pkg_qty' => $resolvedPkgQty,
            'last_used_at' => now(),
        ];

        if ($link === null) {
            SupplierProductLink::create($attributes + [
                'tenant_id' => $line->purchase->tenant_id,
                'supplier_id' => $supplierId,
                'raw_name_normalized' => $normalized,
                'times_confirmed' => 1,
            ]);

            return;
        }

        $link->update($attributes + ['times_confirmed' => $link->times_confirmed + 1]);
    }

    /**
     * Olvidar: el usuario desasoció el renglón o lo marcó como consumo personal.
     * Dejar el vínculo viejo haría que la próxima factura vuelva a sugerir
     * justo lo que se acaba de descartar.
     */
    public function forget(Tenant $tenant, ?int $supplierId, ?string $rawName): void
    {
        if ($supplierId === null || blank($rawName)) {
            return;
        }

        $normalized = $this->fold($rawName);

        if ($normalized === '') {
            return;
        }

        SupplierProductLink::query()
            ->where('tenant_id', $tenant->id)
            ->where('supplier_id', $supplierId)
            ->where('raw_name_normalized', $normalized)
            ->delete();
    }

    /**
     * Mismo criterio que SupplierMatcher::fold() (los comprobantes se imprimen
     * en mayúsculas y sin tildes), más el colapso de espacios: las facturas
     * imprimen "HARINA  000   X 25KG" con espaciado irregular entre columnas,
     * y ese texto tiene que resolver al mismo vínculo mes a mes.
     */
    public function fold(string $value): string
    {
        $folded = mb_strtolower(Str::ascii($value));

        return trim((string) preg_replace('/\s+/', ' ', $folded));
    }

    /**
     * @return array{0: int, 1: string}|null [supplier_id, texto plegado]
     */
    private function keyFor(PurchaseLine $line): ?array
    {
        $supplierId = $line->purchase?->supplier_id;

        if ($supplierId === null || blank($line->raw_name)) {
            return null;
        }

        $normalized = $this->fold($line->raw_name);

        return $normalized === '' ? null : [$supplierId, $normalized];
    }

    /**
     * Ids del catálogo del tenant, por tipo, entre los que apuntan estos vínculos.
     * Incluye los ítems dados de baja a propósito: es la misma regla del
     * proveedor inactivo de PurchaseController::match(). Si el insumo se
     * desactivó, el vínculo sigue siendo el correcto.
     *
     * @param  Collection<int, SupplierProductLink>  $links
     * @return array<string, array<int, int>>
     */
    private function ownedIds(Tenant $tenant, Collection $links): array
    {
        $byType = $links->groupBy('purchaseable_type');
        $owned = [];

        foreach ($byType as $type => $group) {
            $itemType = CatalogItemType::tryFrom((string) $type);

            if ($itemType === null) {
                continue;
            }

            $relation = $itemType === CatalogItemType::Ingredient
                ? $tenant->ingredients()
                : $tenant->packagings();

            $owned[$type] = $relation
                ->whereIn('id', $group->pluck('purchaseable_id')->all())
                ->pluck('id')
                ->all();
        }

        return $owned;
    }
}

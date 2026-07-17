<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Resolves a supplier name read off a scanned document against the tenant's
 * own suppliers. The match is deliberately loose in both directions: what the
 * document prints ("MOLINO SAN JOSÉ S.A.") and what the user typed ("Molino")
 * rarely coincide exactly.
 */
class SupplierMatcher
{
    /**
     * @param  Collection<int, Supplier>  $suppliers
     */
    public function match(?string $name, Collection $suppliers): ?int
    {
        if (blank($name)) {
            return null;
        }

        $needle = $this->fold($name);

        if ($needle === '') {
            return null;
        }

        foreach ($suppliers as $supplier) {
            $hay = $this->fold($supplier->name);

            if ($hay === '') {
                continue;
            }

            if ($hay === $needle || str_contains($hay, $needle) || str_contains($needle, $hay)) {
                return $supplier->id;
            }
        }

        return null;
    }

    /**
     * Los comprobantes se imprimen en mayúsculas y sin tildes ("FERRETERIA
     * ROSALES"), mientras que el usuario carga el proveedor como lo escribe
     * ("Ferretería Rosales"): sin plegar los acentos, esos dos nunca matchean.
     */
    private function fold(string $value): string
    {
        return trim(mb_strtolower(Str::ascii($value)));
    }
}

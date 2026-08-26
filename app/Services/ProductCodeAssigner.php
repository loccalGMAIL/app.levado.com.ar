<?php

namespace App\Services;

use App\Models\Product;

/**
 * Asigna un código EAN-13 interno a los artículos que no tienen código de barras
 * propio, para que todos los artículos tengan un identificador escaneable único
 * (base del lector/POS). Los que traen un barcode real no se tocan.
 */
class ProductCodeAssigner
{
    public function __construct(private readonly Ean13Generator $generator) {}

    /**
     * Asigna un EAN-13 único (por negocio) al artículo si aún no tiene código.
     * Devuelve true si asignó uno.
     */
    public function assignIfMissing(Product $product): bool
    {
        if (filled($product->barcode)) {
            return false;
        }

        $product->barcode = $this->uniqueCodeFor($product->tenant_id);
        $product->save();

        return true;
    }

    /** Genera un EAN-13 que no exista todavía para el negocio (reintenta ante colisión). */
    private function uniqueCodeFor(int $tenantId): string
    {
        do {
            $code = $this->generator->generate();
        } while (Product::where('tenant_id', $tenantId)->where('barcode', $code)->exists());

        return $code;
    }
}

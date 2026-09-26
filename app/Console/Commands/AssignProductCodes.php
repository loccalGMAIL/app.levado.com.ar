<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductCodeAssigner;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * Asigna un código EAN-13 interno a los artículos existentes que todavía no tienen
 * código de barras, para que todos queden con un identificador escaneable único
 * (base del lector/POS). Idempotente: los que ya tienen código no se tocan.
 */
class AssignProductCodes extends Command
{
    protected $signature = 'products:assign-codes
                            {--tenant= : Limitar a un tenant por id}
                            {--dry-run : Mostrar cuántos se codificarían sin escribir nada}';

    protected $description = 'Asigna un código EAN-13 interno a los artículos sin código de barras.';

    public function handle(ProductCodeAssigner $codeAssigner): int
    {
        $tenantId = $this->option('tenant');

        $query = Product::query()
            ->where(fn ($q) => $q->whereNull('barcode')->orWhere('barcode', ''))
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));

        $pending = (clone $query)->count();

        if ($pending === 0) {
            info('Todos los artículos ya tienen código.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            info("Dry-run: se codificarían {$pending} artículo(s).");

            return self::SUCCESS;
        }

        warning("Asignando código a {$pending} artículo(s)…");

        $assigned = 0;
        $query->orderBy('id')->chunkById(200, function ($products) use ($codeAssigner, &$assigned) {
            foreach ($products as $product) {
                if ($codeAssigner->assignIfMissing($product)) {
                    $assigned++;
                }
            }
        });

        info("Se asignó código a {$assigned} artículo(s).");

        return self::SUCCESS;
    }
}

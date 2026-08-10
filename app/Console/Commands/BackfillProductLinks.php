<?php

namespace App\Console\Commands;

use App\Models\PurchaseLine;
use App\Models\SupplierProductLink;
use App\Services\ProductLinkMemory;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Siembra la memoria de vinculación con las decisiones que ya se tomaron antes
 * de que la tabla existiera. Sin esto, un negocio con dos años de facturas
 * arrancaría la feature en cero.
 */
class BackfillProductLinks extends Command
{
    protected $signature = 'purchases:backfill-product-links
                            {--dry-run : Muestra lo que haría sin escribir nada}';

    protected $description = 'Siembra supplier_product_links desde los renglones de compra ya imputados.';

    public function __construct(private readonly ProductLinkMemory $memory)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Sólo decisiones consumadas: un renglón pendiente todavía puede estar
        // mostrando una sugerencia de la IA que nadie confirmó.
        $lines = PurchaseLine::query()
            ->whereNotNull('purchaseable_id')
            ->whereNotNull('purchaseable_type')
            ->whereNotNull('cost_applied_at')
            ->whereNotNull('raw_name')
            ->where('raw_name', '!=', '')
            ->with('purchase')
            ->orderBy('cost_applied_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (PurchaseLine $line) => $line->purchase?->supplier_id !== null);

        if ($lines->isEmpty()) {
            info('No hay renglones imputados de los que aprender.');

            return self::SUCCESS;
        }

        // Recorrido ascendente por fecha de imputación: la última escritura de
        // cada clave gana, así que ante textos repetidos queda la más reciente.
        $candidates = [];
        $conflicts = 0;

        foreach ($lines as $line) {
            $normalized = $this->memory->fold((string) $line->raw_name);

            if ($normalized === '') {
                continue;
            }

            $key = "{$line->purchase->tenant_id}|{$line->purchase->supplier_id}|{$normalized}";

            if (isset($candidates[$key])) {
                $conflicts++;
            }

            $candidates[$key] = [
                'tenant_id' => $line->purchase->tenant_id,
                'supplier_id' => $line->purchase->supplier_id,
                'raw_name_normalized' => $normalized,
                'raw_name_sample' => (string) $line->raw_name,
                'purchaseable_type' => $line->purchaseable_type,
                'purchaseable_id' => $line->purchaseable_id,
            ];
        }

        // Los vínculos que ya existen mandan: son decisiones posteriores al backfill.
        // Solo lectura, sin escritura entrelazada: cursor() es seguro acá (a
        // diferencia del bucle de abajo, que si actualizara filas necesitaría
        // chunkById/lazyById en su lugar).
        // collect() al final: $existing se usa para lookups repetidos (has() por
        // candidata), así que tiene que ser una Collection materializada, no un
        // generador de un solo uso — cursor() sólo abarata la lectura y la
        // hidratación de camino hacia ahí.
        $existing = SupplierProductLink::query()
            ->withoutGlobalScopes()
            ->select('tenant_id', 'supplier_id', 'raw_name_normalized')
            ->cursor()
            ->map(fn (SupplierProductLink $link) => "{$link->tenant_id}|{$link->supplier_id}|{$link->raw_name_normalized}")
            ->collect()
            ->flip();

        $new = collect($candidates)->reject(fn (array $row, string $key) => $existing->has($key));

        if ($new->isEmpty()) {
            info("Nada que sembrar: {$existing->count()} vínculo(s) ya existían.");

            return self::SUCCESS;
        }

        warning("Se van a crear {$new->count()} vínculo(s) desde ".$lines->count().' renglón(es) imputado(s).');

        if ($conflicts > 0) {
            info("{$conflicts} texto(s) repetido(s) resueltos por la imputación más reciente.");
        }

        $this->newLine();
        table(
            headers: ['Tenant', 'Proveedor', 'Texto de la factura', 'Ítem'],
            rows: $new->take(20)->map(fn (array $row) => [
                $row['tenant_id'],
                $row['supplier_id'],
                Str::limit($row['raw_name_sample'], 45),
                "{$row['purchaseable_type']}:{$row['purchaseable_id']}",
            ])->values()->all(),
        );

        if ($new->count() > 20) {
            info('… y '.($new->count() - 20).' más.');
        }

        if ($dryRun) {
            $this->newLine();
            info('Dry run: no se escribió nada.');

            return self::SUCCESS;
        }

        // El divisor queda en null a propósito: no está persistido en ningún lado
        // y derivarlo de la descripción sería recrear el fallback que ya existe.
        foreach ($new->chunk(200) as $chunk) {
            SupplierProductLink::withoutGlobalScopes()->insert(
                $chunk->map(fn (array $row) => $row + [
                    'pkg_qty' => null,
                    'times_confirmed' => 1,
                    'last_used_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->values()->all(),
            );
        }

        info("Se sembraron {$new->count()} vínculo(s).");

        return self::SUCCESS;
    }
}

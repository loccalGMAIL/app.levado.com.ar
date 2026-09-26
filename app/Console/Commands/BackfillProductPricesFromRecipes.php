<?php

namespace App\Console\Commands;

use App\Enums\PricingPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Copia los precios de venta de cada receta (recipe_prices) al producto elaborado
 * vinculado (product_prices), para las celdas que todavía no existen.
 *
 * A diferencia de la migración de backfill (000004), este comando se corre DESPUÉS
 * de `products:from-recipes`: en un deploy nuevo la migración corre con la tabla
 * `products` recién creada (vacía) y no copia nada, así que el precio no llega a
 * `product_prices` (la fuente única de la UI). Este comando cierra ese hueco.
 *
 * Idempotente: solo inserta celdas faltantes (recipe_price sin product_price para
 * ese artículo × lista); nunca pisa un precio ya cargado. `recipe_prices` queda intacta.
 * Todas las celdas se crean con política `manual` (los recipe_prices son precios a mano).
 */
class BackfillProductPricesFromRecipes extends Command
{
    protected $signature = 'products:backfill-prices
                            {--tenant= : Limitar a un tenant por id}
                            {--dry-run : Mostrar qué se copiaría sin escribir nada}';

    protected $description = 'Copia los precios de receta a los artículos elaborados (product_prices) que aún no los tienen.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $missing = DB::table('recipe_prices')
            ->join('products', function ($join) {
                $join->on('products.recipe_id', '=', 'recipe_prices.recipe_id')
                    ->where('products.type', '=', 'manufactured');
            })
            ->leftJoin('product_prices', function ($join) {
                $join->on('product_prices.product_id', '=', 'products.id')
                    ->on('product_prices.price_list_id', '=', 'recipe_prices.price_list_id');
            })
            ->whereNull('product_prices.id')
            ->when($tenantId, fn ($query) => $query->where('recipe_prices.tenant_id', $tenantId))
            ->select(
                'recipe_prices.tenant_id',
                'recipe_prices.price_list_id',
                'products.id as product_id',
                'recipe_prices.price',
            )
            ->get();

        if ($missing->isEmpty()) {
            info('No hay precios de receta pendientes de copiar a los artículos.');

            return self::SUCCESS;
        }

        warning("Se copiarán {$missing->count()} precio(s) de receta a los artículos elaborados.");

        if ($this->option('dry-run')) {
            table(
                ['Tenant', 'Artículo', 'Lista', 'Precio'],
                $missing->take(20)->map(fn ($row) => [
                    $row->tenant_id,
                    $row->product_id,
                    $row->price_list_id,
                    number_format((float) $row->price, 2, ',', '.'),
                ])->all(),
            );
            info('Dry-run: no se escribió nada'.($missing->count() > 20 ? ' (se muestran los primeros 20).' : '.'));

            return self::SUCCESS;
        }

        $now = now();
        $missing
            ->map(fn ($row) => [
                'tenant_id' => $row->tenant_id,
                'price_list_id' => $row->price_list_id,
                'product_id' => $row->product_id,
                'price' => $row->price,
                'policy_type' => PricingPolicy::Manual->value,
                'policy_value' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->chunk(500)
            ->each(fn ($chunk) => DB::table('product_prices')->insert($chunk->all()));

        info("Se copiaron {$missing->count()} precio(s) a los artículos elaborados.");

        return self::SUCCESS;
    }
}

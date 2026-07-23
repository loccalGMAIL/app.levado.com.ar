<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\ArticlePriceRecalculator;
use Illuminate\Console\Command;

/**
 * Recalcula el precio cacheado (product_prices.price) de todas las celdas con
 * política (margen/recargo) desde el costo vigente de cada artículo. Red de
 * seguridad para dejar los precios al día tras cambios de costo/overhead.
 */
class RefreshProductPrices extends Command
{
    protected $signature = 'products:refresh-prices';

    protected $description = 'Recalcula el precio cacheado de las celdas con política de precio (margen/recargo).';

    public function handle(ArticlePriceRecalculator $recalculator): int
    {
        Tenant::query()->each(fn (Tenant $tenant) => $recalculator->recomputeForTenant($tenant));

        $this->info('Precios con política recalculados.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use App\Enums\PricingPolicy;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;

/**
 * Recalcula el precio cacheado (`product_prices.price`) de las celdas con política
 * de precio (margen/recargo) cuando cambia el costo del artículo. Los precios
 * manuales no se tocan. Mantiene la columna `price` al día para el SQL del Dashboard.
 */
class ArticlePriceRecalculator
{
    public function recompute(Product $product, ?float $overheadPerHour = null): void
    {
        $product->loadMissing('recipe', 'tenant');
        $overheadPerHour ??= $product->tenant->overheadPerHour() ?? 0.0;
        $cost = $product->fullCost($overheadPerHour);

        ProductPrice::where('product_id', $product->id)
            ->where('policy_type', '!=', PricingPolicy::Manual->value)
            ->get()
            ->each(function (ProductPrice $pp) use ($cost) {
                $value = $pp->policy_value !== null ? (float) $pp->policy_value : null;
                $newPrice = $pp->policy_type->priceFor($cost, $value);

                if ($pp->price === null ? $newPrice !== null : (float) $pp->price !== (float) $newPrice) {
                    $pp->update(['price' => $newPrice]);
                }
            });
    }

    /** Recalcula todos los artículos del tenant con alguna celda con política (ej. cambió el overhead). */
    public function recomputeForTenant(Tenant $tenant): void
    {
        $overheadPerHour = $tenant->overheadPerHour() ?? 0.0;

        $tenant->products()
            ->with('recipe')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('product_prices')
                    ->whereColumn('product_prices.product_id', 'products.id')
                    ->where('product_prices.policy_type', '!=', PricingPolicy::Manual->value);
            })
            ->get()
            ->each(fn (Product $product) => $this->recompute($product, $overheadPerHour));
    }
}

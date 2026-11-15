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
    /**
     * Recalcula el artículo y, si es componente de algún combo, también esos
     * combos: su costo es la suma del de sus componentes.
     */
    public function recompute(Product $product, ?float $overheadPerHour = null): void
    {
        $product->loadMissing('tenant');
        $overheadPerHour ??= $product->tenant->overheadPerHour() ?? 0.0;

        $this->recomputeOne($product, $overheadPerHour);

        if ($product->isCombo()) {
            return;
        }

        Product::query()
            ->with('comboLines.component.recipe')
            ->whereHas('comboLines', fn ($query) => $query->where('product_id', $product->id))
            ->get()
            ->each(fn (Product $combo) => $this->recomputeOne($combo, $overheadPerHour));
    }

    private function recomputeOne(Product $product, float $overheadPerHour): void
    {
        $product->loadMissing('recipe');
        $cost = $product->fullCost($overheadPerHour);

        ProductPrice::where('product_id', $product->id)
            ->where('policy_type', '!=', PricingPolicy::Manual->value)
            ->get()
            ->each(function (ProductPrice $pp) use ($cost, $product) {
                $value = $pp->policy_value !== null ? (float) $pp->policy_value : null;
                $newPrice = $pp->policy_type->priceFor($cost, $value);

                if ($pp->price === null ? $newPrice !== null : (float) $pp->price !== (float) $newPrice) {
                    $pp->update(['price' => $newPrice]);

                    // Mismo rastro que deja ProductPriceWriter: sin esto el historial
                    // de precios tenía huecos justo cuando el precio se movía solo.
                    if ($newPrice !== null) {
                        $product->priceLogs()->create([
                            'price_list_id' => $pp->price_list_id,
                            'price' => $newPrice,
                            'recorded_at' => now(),
                        ]);
                    }
                }
            });
    }

    /** Recalcula todos los artículos del tenant con alguna celda con política (ej. cambió el overhead). */
    public function recomputeForTenant(Tenant $tenant): void
    {
        $overheadPerHour = $tenant->overheadPerHour() ?? 0.0;

        $tenant->products()
            ->with(['recipe', 'comboLines.component.recipe'])
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('product_prices')
                    ->whereColumn('product_prices.product_id', 'products.id')
                    ->where('product_prices.policy_type', '!=', PricingPolicy::Manual->value);
            })
            ->get()
            ->each(fn (Product $product) => $this->recomputeOne($product, $overheadPerHour));
    }
}

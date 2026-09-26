<?php

namespace App\Services;

use App\Enums\PricingPolicy;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;

class ProductPriceWriter
{
    /**
     * Setea el precio MANUAL de un producto en una lista. Null elimina el precio.
     * Loguea en product_price_logs solo cuando el monto es nuevo o cambió.
     */
    public function set(Product $product, PriceList $priceList, ?float $price): ?ProductPrice
    {
        $existing = ProductPrice::where('price_list_id', $priceList->id)
            ->where('product_id', $product->id)
            ->first();

        if ($price === null) {
            $existing?->delete();

            return null;
        }

        $priceChanged = $existing === null || (float) $existing->price !== $price;

        $productPrice = ProductPrice::updateOrCreate(
            ['price_list_id' => $priceList->id, 'product_id' => $product->id],
            ['tenant_id' => $product->tenant_id, 'price' => $price, 'policy_type' => PricingPolicy::Manual->value, 'policy_value' => null],
        );

        if ($priceChanged) {
            $product->priceLogs()->create([
                'price_list_id' => $priceList->id,
                'price' => $price,
                'recorded_at' => now(),
            ]);
        }

        return $productPrice;
    }

    /**
     * Setea una política de precio (margen/recargo) sobre el costo total del artículo.
     * Guarda la política y el precio computado y cacheado. Manual delega en set().
     */
    public function setPolicy(Product $product, PriceList $priceList, PricingPolicy $policy, ?float $value, ?float $overheadPerHour = null): ?ProductPrice
    {
        if ($policy === PricingPolicy::Manual) {
            return $this->set($product, $priceList, $value);
        }

        $overheadPerHour ??= $product->tenant->overheadPerHour() ?? 0.0;
        $price = $policy->priceFor($product->fullCost($overheadPerHour), $value);

        $existing = ProductPrice::where('price_list_id', $priceList->id)
            ->where('product_id', $product->id)
            ->first();
        $changed = $existing === null
            || $existing->policy_type !== $policy
            || ($existing->policy_value !== null ? (float) $existing->policy_value : null) !== $value;

        $productPrice = ProductPrice::updateOrCreate(
            ['price_list_id' => $priceList->id, 'product_id' => $product->id],
            ['tenant_id' => $product->tenant_id, 'price' => $price, 'policy_type' => $policy->value, 'policy_value' => $value],
        );

        if ($changed && $price !== null) {
            $product->priceLogs()->create([
                'price_list_id' => $priceList->id,
                'price' => $price,
                'recorded_at' => now(),
            ]);
        }

        return $productPrice;
    }
}

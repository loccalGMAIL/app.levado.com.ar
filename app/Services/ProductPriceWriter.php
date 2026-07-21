<?php

namespace App\Services;

use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductPrice;

class ProductPriceWriter
{
    /**
     * Setea el precio de un producto en una lista. Null elimina el precio.
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
            ['tenant_id' => $product->tenant_id, 'price' => $price],
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
}

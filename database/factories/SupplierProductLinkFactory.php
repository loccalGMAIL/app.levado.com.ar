<?php

namespace Database\Factories;

use App\Enums\CatalogItemType;
use App\Models\Supplier;
use App\Models\SupplierProductLink;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierProductLink>
 */
class SupplierProductLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rawName = mb_strtoupper(fake()->words(3, true));

        return [
            'tenant_id' => Tenant::factory(),
            'supplier_id' => Supplier::factory(),
            'raw_name_normalized' => mb_strtolower($rawName),
            'raw_name_sample' => $rawName,
            'purchaseable_type' => CatalogItemType::Ingredient->value,
            'purchaseable_id' => 1,
            'pkg_qty' => null,
            'times_confirmed' => 1,
            'last_used_at' => null,
        ];
    }
}

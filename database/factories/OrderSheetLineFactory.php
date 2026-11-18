<?php

namespace Database\Factories;

use App\Models\OrderSheetLine;
use App\Models\OrderSheetWeek;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderSheetLine>
 */
class OrderSheetLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_sheet_week_id' => OrderSheetWeek::factory(),
            // El artículo es del mismo negocio que la semana.
            'product_id' => fn (array $attributes) => Product::factory()->create([
                'tenant_id' => OrderSheetWeek::withoutGlobalScopes()->findOrFail($attributes['order_sheet_week_id'])->tenant_id,
            ])->id,
            'position' => 0,
            'day_1' => 0,
            'day_2' => 0,
            'day_3' => 0,
            'day_4' => 0,
            'day_5' => 0,
            'day_6' => 0,
            'day_7' => 0,
        ];
    }

    /**
     * @param  array<int, float|int>  $quantities  Lunes a domingo.
     */
    public function withQuantities(array $quantities): static
    {
        $attributes = [];
        foreach (array_values($quantities) as $index => $quantity) {
            $attributes['day_'.($index + 1)] = $quantity;
        }

        return $this->state($attributes);
    }
}

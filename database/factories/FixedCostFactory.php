<?php

namespace Database\Factories;

use App\Models\FixedCost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FixedCost>
 */
class FixedCostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'monthly_amount' => fake()->randomFloat(2, 500, 50000),
            'active' => true,
        ];
    }

    /**
     * Siembra $months logs mensuales consecutivos terminando en el mes en
     * curso, todos con el `monthly_amount` del modelo -para probar
     * carry-forward y timeline sin repetir el armado en cada test.
     */
    public function withHistory(int $months): static
    {
        return $this->afterCreating(function (FixedCost $fixedCost) use ($months) {
            $current = Carbon::now()->startOfMonth();

            foreach (range($months - 1, 0) as $monthsAgo) {
                $fixedCost->logs()->create([
                    'monthly_amount' => $fixedCost->monthly_amount,
                    'period' => $current->copy()->subMonths($monthsAgo),
                ]);
            }
        });
    }
}

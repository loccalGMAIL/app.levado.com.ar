<?php

namespace App\Services;

use App\Models\Recipe;

class RecipeCostCalculator
{
    public function __construct(private UnitConverter $converter) {}

    /**
     * @return array{ingredient_cost: float, packaging_cost: float, labor_cost: float, total_cost: float, cost_per_unit: float|null}
     */
    public function calculate(Recipe $recipe): array
    {
        $recipe->loadMissing([
            'ingredientLines.ingredient',
            'packagingLines.packaging',
            'laborLines.laborType',
        ]);

        $ingredientCost = 0.0;
        foreach ($recipe->ingredientLines as $line) {
            $converted = $this->converter->convert(
                (float) $line->quantity,
                $line->unit,
                $line->ingredient->unit
            );
            if ($converted !== null) {
                $ingredientCost += $converted * (float) $line->ingredient->cost_per_unit;
            }
        }

        $packagingCost = $recipe->packagingLines->sum(
            fn ($l) => (float) $l->quantity * (float) $l->packaging->cost_per_unit
        );

        $laborCost = $recipe->laborLines->sum(
            fn ($l) => (float) $l->hours * (float) $l->laborType->hourly_rate
        );

        $totalCost = $ingredientCost + $packagingCost + $laborCost;
        $costPerUnit = (float) $recipe->yield_quantity > 0
            ? $totalCost / (float) $recipe->yield_quantity
            : null;

        return [
            'ingredient_cost' => $ingredientCost,
            'packaging_cost' => $packagingCost,
            'labor_cost' => $laborCost,
            'total_cost' => $totalCost,
            'cost_per_unit' => $costPerUnit,
        ];
    }
}

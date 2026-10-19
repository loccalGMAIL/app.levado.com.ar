<?php

namespace App\Services;

use App\Models\Recipe;

class RecipeCostCalculator
{
    public function __construct(private UnitConverter $converter) {}

    /**
     * @return array{ingredient_cost: float, packaging_cost: float, labor_cost: float, subrecipe_cost: float, total_cost: float, cost_per_unit: float|null, total_labor_hours: float}
     */
    public function calculate(Recipe $recipe): array
    {
        $recipe->loadMissing([
            'ingredientLines.ingredient',
            'productLines.product',
            'packagingLines.packaging',
            'laborLines.laborType',
            'subrecipeLines.childRecipe',
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

        // Un artículo de reventa usado como insumo cuenta como ingrediente.
        foreach ($recipe->productLines as $line) {
            $converted = $this->converter->convert(
                (float) $line->quantity,
                $line->unit,
                $line->product->unit
            );
            if ($converted !== null) {
                $ingredientCost += $converted * (float) $line->product->cost_per_unit;
            }
        }

        $packagingCost = $recipe->packagingLines->sum(
            fn ($l) => (float) $l->quantity * (float) $l->packaging->cost_per_unit
        );

        $laborHours = $recipe->laborLines->sum(fn ($l) => (float) $l->hours);
        $laborCost = $recipe->laborLines->sum(
            fn ($l) => (float) $l->hours * (float) $l->laborType->hourly_rate
        );

        $subrecipeCost = 0.0;
        foreach ($recipe->subrecipeLines as $line) {
            $child = $line->childRecipe;
            if ($child->unit_cost === null) {
                continue;
            }
            $converted = $this->converter->convert(
                (float) $line->quantity_used,
                $line->unit,
                $child->yield_unit
            );
            if ($converted !== null) {
                $subrecipeCost += $converted * (float) $child->unit_cost;
            }
        }

        $totalCost = $ingredientCost + $packagingCost + $laborCost + $subrecipeCost;
        $costPerUnit = (float) $recipe->yield_quantity > 0
            ? $totalCost / (float) $recipe->yield_quantity
            : null;

        return [
            'ingredient_cost' => $ingredientCost,
            'packaging_cost' => $packagingCost,
            'labor_cost' => $laborCost,
            'subrecipe_cost' => $subrecipeCost,
            'total_cost' => $totalCost,
            'cost_per_unit' => $costPerUnit,
            'total_labor_hours' => $laborHours,
        ];
    }
}

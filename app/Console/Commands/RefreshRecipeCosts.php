<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use App\Services\RecipeCostCalculator;
use Illuminate\Console\Command;

class RefreshRecipeCosts extends Command
{
    protected $signature = 'recipes:refresh-costs';

    protected $description = 'Recalcula los caches unit_cost y labor_hours de todas las recetas (backfill tras deploy)';

    public function handle(RecipeCostCalculator $calculator): int
    {
        // Pasadas sucesivas hasta converger: cada pasada corrige un nivel de
        // anidamiento de sub-recetas (el costo del padre depende del unit_cost
        // ya recalculado del hijo). El tope de 10 solo protege de un ciclo que
        // el guard isAncestor() no debería permitir.
        $passes = 0;

        do {
            $changed = false;
            $passes++;

            Recipe::query()->with([
                'ingredientLines.ingredient',
                'packagingLines.packaging',
                'laborLines.laborType',
                'subrecipeLines.childRecipe',
            ])->chunkById(100, function ($recipes) use ($calculator, &$changed) {
                foreach ($recipes as $recipe) {
                    $costs = $calculator->calculate($recipe);
                    $newUnitCost = $costs['cost_per_unit'] !== null ? round($costs['cost_per_unit'], 4) : null;
                    $newLaborHours = round($costs['total_labor_hours'], 2);

                    $currentUnitCost = $recipe->unit_cost !== null ? (float) $recipe->unit_cost : null;
                    $currentLaborHours = $recipe->labor_hours !== null ? (float) $recipe->labor_hours : null;
                    $dirty = $currentUnitCost !== $newUnitCost || $currentLaborHours !== $newLaborHours;

                    if ($dirty) {
                        $recipe->updateQuietly(['unit_cost' => $newUnitCost, 'labor_hours' => $newLaborHours]);
                        $changed = true;
                    }
                }
            });
        } while ($changed && $passes < 10);

        $this->info(Recipe::count()." recetas al día (unit_cost y labor_hours, {$passes} pasada(s)).");

        return self::SUCCESS;
    }
}

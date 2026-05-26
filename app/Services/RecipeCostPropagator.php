<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeLaborLine;
use App\Models\RecipePackagingLine;
use Illuminate\Support\Facades\DB;

class RecipeCostPropagator
{
    public function __construct(private RecipeCostCalculator $calculator) {}

    /**
     * Recalculate unit_cost for $recipe, then BFS upward through all parent recipes.
     */
    public function propagateFrom(Recipe $recipe): void
    {
        $visited = [];
        $queue = [$recipe->id];

        while (! empty($queue)) {
            $id = array_shift($queue);

            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;

            $node = Recipe::with([
                'ingredientLines.ingredient',
                'packagingLines.packaging',
                'laborLines.laborType',
                'subrecipeLines.childRecipe',
            ])->find($id);

            if (! $node) {
                continue;
            }

            $costs = $this->calculator->calculate($node);
            $node->update(['unit_cost' => $costs['cost_per_unit']]);

            $parentIds = $node->parentSubrecipeLines()->pluck('recipe_id')->toArray();

            foreach ($parentIds as $parentId) {
                if (! isset($visited[$parentId])) {
                    $queue[] = $parentId;
                }
            }
        }
    }

    /**
     * After an ingredient's cost_per_unit changes, propagate from every recipe using it.
     */
    public function propagateFromIngredient(int $ingredientId): void
    {
        $recipeIds = RecipeIngredientLine::where('ingredient_id', $ingredientId)
            ->pluck('recipe_id')
            ->unique();

        foreach ($recipeIds as $recipeId) {
            $recipe = Recipe::find($recipeId);
            if ($recipe) {
                $this->propagateFrom($recipe);
            }
        }
    }

    /**
     * After a packaging's cost_per_unit changes, propagate from every recipe using it.
     */
    public function propagateFromPackaging(int $packagingId): void
    {
        $recipeIds = RecipePackagingLine::where('packaging_id', $packagingId)
            ->pluck('recipe_id')
            ->unique();

        foreach ($recipeIds as $recipeId) {
            $recipe = Recipe::find($recipeId);
            if ($recipe) {
                $this->propagateFrom($recipe);
            }
        }
    }

    /**
     * After a labor type's hourly_rate changes, propagate from every recipe using it.
     */
    public function propagateFromLaborType(int $laborTypeId): void
    {
        $recipeIds = RecipeLaborLine::where('labor_type_id', $laborTypeId)
            ->pluck('recipe_id')
            ->unique();

        foreach ($recipeIds as $recipeId) {
            $recipe = Recipe::find($recipeId);
            if ($recipe) {
                $this->propagateFrom($recipe);
            }
        }
    }

    /**
     * DAG cycle check. Returns true if $ancestorId is reachable by walking UP
     * from $descendantId through recipe_subrecipe_lines.child_recipe_id.
     */
    public function isAncestor(int $ancestorId, int $descendantId, int $tenantId): bool
    {
        $visited = [];
        $queue = [$descendantId];

        while (! empty($queue)) {
            $current = array_shift($queue);

            if ($current === $ancestorId) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $parentIds = DB::table('recipe_subrecipe_lines')
                ->join('recipes', 'recipes.id', '=', 'recipe_subrecipe_lines.recipe_id')
                ->where('recipe_subrecipe_lines.child_recipe_id', $current)
                ->where('recipes.tenant_id', $tenantId)
                ->pluck('recipe_subrecipe_lines.recipe_id')
                ->toArray();

            foreach ($parentIds as $pid) {
                if (! isset($visited[$pid])) {
                    $queue[] = $pid;
                }
            }
        }

        return false;
    }
}

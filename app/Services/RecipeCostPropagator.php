<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeLaborLine;
use App\Models\RecipePackagingLine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecipeCostPropagator
{
    public function __construct(private RecipeCostCalculator $calculator) {}

    /**
     * Recalculate unit_cost for $recipe, then BFS upward through all parent recipes.
     *
     * Serializada por receta (R13): el autosave de /recipes/{id} dispara varios
     * PATCH casi simultáneos sobre líneas distintas de la misma receta, y sin
     * este lock dos recorridos superpuestos pueden calcular el costo de un
     * ancestro común con el valor viejo del hijo. No afecta la concurrencia
     * entre recetas distintas — el lock es por recipe_id.
     */
    public function propagateFrom(Recipe $recipe): void
    {
        Cache::lock("recipe-propagation:{$recipe->id}", 10)
            ->block(5, fn () => $this->propagateFromLocked($recipe));
    }

    private function propagateFromLocked(Recipe $recipe): void
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
            $node->update([
                'unit_cost' => $costs['cost_per_unit'],
                'labor_hours' => $costs['total_labor_hours'],
            ]);

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
            ->distinct()
            ->pluck('recipe_id');

        Recipe::whereIn('id', $recipeIds)->select('id')->get()->each(fn (Recipe $recipe) => $this->propagateFrom($recipe));
    }

    /**
     * After a packaging's cost_per_unit changes, propagate from every recipe using it.
     */
    public function propagateFromPackaging(int $packagingId): void
    {
        $recipeIds = RecipePackagingLine::where('packaging_id', $packagingId)
            ->distinct()
            ->pluck('recipe_id');

        Recipe::whereIn('id', $recipeIds)->select('id')->get()->each(fn (Recipe $recipe) => $this->propagateFrom($recipe));
    }

    /**
     * After a labor type's hourly_rate changes, propagate from every recipe using it.
     */
    public function propagateFromLaborType(int $laborTypeId): void
    {
        $recipeIds = RecipeLaborLine::where('labor_type_id', $laborTypeId)
            ->distinct()
            ->pluck('recipe_id');

        Recipe::whereIn('id', $recipeIds)->select('id')->get()->each(fn (Recipe $recipe) => $this->propagateFrom($recipe));
    }

    /**
     * DAG cycle check. Returns true if $ancestorId is reachable by walking UP
     * from $descendantId through recipe_subrecipe_lines.child_recipe_id.
     */
    public function isAncestor(int $ancestorId, int $descendantId, int $tenantId): bool
    {
        // Un nodo es trivialmente "ancestro" de sí mismo (guarda contra que una
        // receta se agregue como su propia sub-receta); ancestorIdsOf() excluye
        // la semilla a propósito porque devuelve el cierre de ancestros reales.
        if ($ancestorId === $descendantId) {
            return true;
        }

        return in_array($ancestorId, $this->ancestorIdsOf($descendantId, $tenantId), true);
    }

    /**
     * Ids de todas las recetas que usan a $recipeId como sub-receta, directa o
     * indirectamente (el cierre completo de ancestros). BFS por niveles: una
     * query por nivel de profundidad del árbol, no una por nodo visitado.
     *
     * @return array<int, int>
     */
    public function ancestorIdsOf(int $recipeId, int $tenantId): array
    {
        $found = [];
        $level = [$recipeId];

        while ($level !== []) {
            $parents = DB::table('recipe_subrecipe_lines')
                ->join('recipes', 'recipes.id', '=', 'recipe_subrecipe_lines.recipe_id')
                ->whereIn('recipe_subrecipe_lines.child_recipe_id', $level)
                ->where('recipes.tenant_id', $tenantId)
                ->distinct()
                ->pluck('recipe_subrecipe_lines.recipe_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $level = array_values(array_diff($parents, $found));
            $found = array_merge($found, $level);
        }

        return $found;
    }
}

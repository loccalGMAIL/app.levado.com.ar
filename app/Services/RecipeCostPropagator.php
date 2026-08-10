<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeLaborLine;
use App\Models\RecipePackagingLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecipeCostPropagator
{
    public function __construct(private RecipeCostCalculator $calculator) {}

    /**
     * Recalculate unit_cost for $recipe, then propagate upward through all parent recipes.
     */
    public function propagateFrom(Recipe $recipe): void
    {
        $this->propagateManyFrom([$recipe->id]);
    }

    /**
     * Recalcula unit_cost de todas las recetas semilla y de todos sus
     * ancestros, en tres fases separadas (recorrido → carga → recálculo) en
     * vez de una query por nodo visitado:
     *
     *  1. Cierre de ancestros por NIVELES: O(profundidad) queries, no O(nodos).
     *  2. Una sola carga para todo el cierre: 5 queries fijas.
     *  3. Recálculo en orden topológico (hijos antes que padres), en memoria.
     *
     * Serializada por nodo tocado (R13): el autosave de /recipes/{id} dispara
     * varios PATCH casi simultáneos, y sin este lock dos propagaciones
     * superpuestas pueden calcular el costo de un ancestro común con el valor
     * viejo del hijo. Los locks se adquieren en orden ascendente de id para
     * que dos lotes con nodos en común no se abracen (deadlock).
     *
     * @param  iterable<int>  $seedIds
     */
    public function propagateManyFrom(iterable $seedIds): void
    {
        $seeds = collect($seedIds)->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($seeds === []) {
            return;
        }

        $closure = $this->propagationClosure($seeds);

        if ($closure === []) {
            return;
        }

        sort($closure);
        $locks = array_map(fn (int $id) => Cache::lock("recipe-propagation:{$id}", 10), $closure);

        try {
            foreach ($locks as $lock) {
                $lock->block(5);
            }

            $this->propagateClosure($closure);
        } finally {
            foreach ($locks as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * Las recetas semilla + todos sus ancestros (recetas que las usan como
     * sub-receta, directa o indirectamente). BFS por niveles, sin scopear por
     * tenant: mismo comportamiento que tenía el recorrido nodo-por-nodo
     * original, que tampoco filtraba — la integridad referencial de
     * recipe_subrecipe_lines ya garantiza que no cruza tenants.
     *
     * @param  array<int, int>  $seedIds
     * @return array<int, int>
     */
    private function propagationClosure(array $seedIds): array
    {
        $found = $seedIds;
        $level = $seedIds;

        while ($level !== []) {
            $parents = DB::table('recipe_subrecipe_lines')
                ->whereIn('child_recipe_id', $level)
                ->distinct()
                ->pluck('recipe_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $level = array_values(array_diff($parents, $found));
            $found = array_merge($found, $level);
        }

        return $found;
    }

    /**
     * @param  array<int, int>  $closure
     */
    private function propagateClosure(array $closure): void
    {
        $recipes = Recipe::with([
            'ingredientLines.ingredient',
            'packagingLines.packaging',
            'laborLines.laborType',
            'subrecipeLines.childRecipe',
        ])
            ->whereIn('id', $closure)
            ->get()
            ->keyBy('id');

        // CLAVE: re-vincula childRecipe a la MISMA instancia que vive en
        // $recipes, en todas las líneas del cierre (no solo las de la receta
        // que se está por recalcular). Eager loading hidrata cada
        // subrecipeLine->childRecipe como un objeto aparte aunque sea la
        // misma fila; sin este relink, cuando más abajo se muta
        // $recipes->get($id)->unit_cost, ningún renglón que apunte a esa
        // receta se entera, y el padre calcularía con el valor viejo de BD.
        foreach ($recipes as $recipe) {
            foreach ($recipe->subrecipeLines as $line) {
                if ($fresh = $recipes->get($line->child_recipe_id)) {
                    $line->setRelation('childRecipe', $fresh);
                }
            }
        }

        foreach ($this->topologicalOrder($recipes) as $recipe) {
            $costs = $this->calculator->calculate($recipe);

            $recipe->unit_cost = $costs['cost_per_unit'];
            $recipe->labor_hours = $costs['total_labor_hours'];
            $recipe->save();
        }
    }

    /**
     * Orden topológico (DFS post-order): cada receta aparece después de todas
     * las recetas que usa como sub-receta, para que su unit_cost ya esté
     * recalculado cuando le toca el turno a quien la usa.
     *
     * @param  Collection<int, Recipe>  $recipes  keyed by id
     * @return list<Recipe>
     */
    private function topologicalOrder(Collection $recipes): array
    {
        $ordered = [];
        $visited = [];

        $visit = function (Recipe $recipe) use (&$visit, &$ordered, &$visited, $recipes) {
            if (isset($visited[$recipe->id])) {
                return;
            }
            $visited[$recipe->id] = true;

            foreach ($recipe->subrecipeLines as $line) {
                $child = $recipes->get($line->child_recipe_id);
                if ($child !== null) {
                    $visit($child);
                }
            }

            $ordered[] = $recipe;
        };

        foreach ($recipes as $recipe) {
            $visit($recipe);
        }

        return $ordered;
    }

    /**
     * After an ingredient's cost_per_unit changes, propagate from every recipe using it.
     */
    public function propagateFromIngredient(int $ingredientId): void
    {
        $this->propagateManyFrom($this->recipeIdsUsingIngredients([$ingredientId]));
    }

    /**
     * After a packaging's cost_per_unit changes, propagate from every recipe using it.
     */
    public function propagateFromPackaging(int $packagingId): void
    {
        $this->propagateManyFrom($this->recipeIdsUsingPackagings([$packagingId]));
    }

    /**
     * After a labor type's hourly_rate changes, propagate from every recipe using it.
     */
    public function propagateFromLaborType(int $laborTypeId): void
    {
        $recipeIds = RecipeLaborLine::where('labor_type_id', $laborTypeId)
            ->distinct()
            ->pluck('recipe_id');

        $this->propagateManyFrom($recipeIds);
    }

    /**
     * Ids de las recetas que usan alguno de estos ingredientes, sin propagar.
     * Para lotes (N8): resolver los ids afectados de varios ítems tocados y
     * propagar una sola vez al final, en vez de una vez por ítem.
     *
     * @param  array<int, int>  $ingredientIds
     * @return array<int, int>
     */
    public function recipeIdsUsingIngredients(array $ingredientIds): array
    {
        if ($ingredientIds === []) {
            return [];
        }

        return RecipeIngredientLine::whereIn('ingredient_id', $ingredientIds)
            ->distinct()
            ->pluck('recipe_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Ids de las recetas que usan alguno de estos descartables, sin propagar.
     *
     * @param  array<int, int>  $packagingIds
     * @return array<int, int>
     */
    public function recipeIdsUsingPackagings(array $packagingIds): array
    {
        if ($packagingIds === []) {
            return [];
        }

        return RecipePackagingLine::whereIn('packaging_id', $packagingIds)
            ->distinct()
            ->pluck('recipe_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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

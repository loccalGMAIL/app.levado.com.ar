<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeLaborLine;
use App\Models\RecipePackagingLine;
use Illuminate\Support\Facades\DB;

/**
 * Mantiene los caches unit_cost / labor_hours de las recetas cuando cambia
 * algo de lo que dependen (un costo de insumo, una tarifa, una línea).
 *
 * Todo termina en recalculate(): se descubre el conjunto afectado (las recetas
 * semilla más TODOS sus ancestros) con una query por nivel del árbol, se ordena
 * topológicamente para que ninguna receta se recalcule antes que las sub-recetas
 * de las que depende, y recién ahí se hidrata y se escribe, de a lotes.
 */
class RecipeCostPropagator
{
    /** Recetas que se hidratan por vuelta al recalcular. */
    private const CHUNK = 100;

    /**
     * Ids acumulados mientras corre batch(); null fuera de un batch.
     *
     * @var array<int, true>|null
     */
    private ?array $deferred = null;

    public function __construct(private RecipeCostCalculator $calculator) {}

    /**
     * Agrupa en un único recálculo todas las propagaciones que dispare
     * $callback. Imputar los 30 renglones de una factura pasa de 30 recorridos
     * completos del árbol de recetas a uno solo.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function batch(callable $callback): mixed
    {
        // Anidado: el batch de afuera es el que decide cuándo se recalcula.
        if ($this->deferred !== null) {
            return $callback();
        }

        $this->deferred = [];

        try {
            return $callback();
        } finally {
            $seeds = $this->deferred;
            $this->deferred = null;

            if (! empty($seeds)) {
                $this->recalculate(array_keys($seeds));
            }
        }
    }

    /**
     * Recalcula $recipe y propaga hacia arriba a todas las recetas que la usan.
     */
    public function propagateFrom(Recipe $recipe): void
    {
        $this->propagate([$recipe->id]);
    }

    /**
     * Tras cambiar el cost_per_unit de un insumo, propaga desde cada receta que lo usa.
     */
    public function propagateFromIngredient(int $ingredientId): void
    {
        $this->propagate(
            RecipeIngredientLine::where('ingredient_id', $ingredientId)->pluck('recipe_id')->all()
        );
    }

    /**
     * Tras cambiar el cost_per_unit de un descartable, propaga desde cada receta que lo usa.
     */
    public function propagateFromPackaging(int $packagingId): void
    {
        $this->propagate(
            RecipePackagingLine::where('packaging_id', $packagingId)->pluck('recipe_id')->all()
        );
    }

    /**
     * Tras cambiar la tarifa horaria de un rol, propaga desde cada receta que lo usa.
     */
    public function propagateFromLaborType(int $laborTypeId): void
    {
        $this->propagate(
            RecipeLaborLine::where('labor_type_id', $laborTypeId)->pluck('recipe_id')->all()
        );
    }

    /**
     * Chequeo de ciclos del DAG: true si se llega a $ancestorId caminando hacia
     * ARRIBA desde $descendantId por recipe_subrecipe_lines.child_recipe_id.
     */
    public function isAncestor(int $ancestorId, int $descendantId, int $tenantId): bool
    {
        return $ancestorId === $descendantId
            || in_array($ancestorId, $this->ancestorsOf($descendantId, $tenantId), true);
    }

    /**
     * Ids de todas las recetas del tenant que están por encima de $recipeId en
     * el árbol (las que la usan, las que usan a ésas, …).
     *
     * Un solo recorrido para responder por todos los candidatos a la vez:
     * preguntar isAncestor() uno por uno re-recorría el mismo árbol N veces.
     *
     * @return array<int, int>
     */
    public function ancestorsOf(int $recipeId, int $tenantId): array
    {
        $ancestors = [];
        $frontier = [$recipeId];

        while ($frontier !== []) {
            $parentIds = DB::table('recipe_subrecipe_lines')
                ->join('recipes', 'recipes.id', '=', 'recipe_subrecipe_lines.recipe_id')
                ->whereIn('recipe_subrecipe_lines.child_recipe_id', $frontier)
                ->where('recipes.tenant_id', $tenantId)
                ->distinct()
                ->pluck('recipe_subrecipe_lines.recipe_id');

            $frontier = [];

            foreach ($parentIds as $parentId) {
                $parentId = (int) $parentId;

                if ($parentId !== $recipeId && ! isset($ancestors[$parentId])) {
                    $ancestors[$parentId] = true;
                    $frontier[] = $parentId;
                }
            }
        }

        return array_keys($ancestors);
    }

    /**
     * @param  array<int, int|string>  $recipeIds
     */
    private function propagate(array $recipeIds): void
    {
        $recipeIds = array_values(array_unique(array_map('intval', $recipeIds)));

        if ($recipeIds === []) {
            return;
        }

        if ($this->deferred !== null) {
            foreach ($recipeIds as $id) {
                $this->deferred[$id] = true;
            }

            return;
        }

        $this->recalculate($recipeIds);
    }

    /**
     * @param  array<int, int>  $seedIds
     */
    private function recalculate(array $seedIds): void
    {
        /** @var array<int, float|null> costos ya recalculados en esta pasada */
        $recalculated = [];

        foreach (array_chunk($this->topologicalOrder($seedIds), self::CHUNK) as $chunk) {
            $nodes = Recipe::with([
                'ingredientLines.ingredient',
                'packagingLines.packaging',
                'laborLines.laborType',
                'subrecipeLines.childRecipe',
            ])->whereIn('id', $chunk)->get()->keyBy('id');

            foreach ($chunk as $id) {
                $node = $nodes->get($id);

                if ($node === null) {
                    continue;
                }

                // La instancia hidratada de la sub-receta trae el unit_cost que
                // había en la base al abrir el lote; si ya se recalculó en esta
                // misma pasada (va antes en el orden topológico), vale el nuevo.
                foreach ($node->subrecipeLines as $line) {
                    $child = $line->childRecipe;

                    if ($child !== null && array_key_exists($child->id, $recalculated)) {
                        $child->unit_cost = $recalculated[$child->id];
                    }
                }

                $costs = $this->calculator->calculate($node);
                $recalculated[$id] = $costs['cost_per_unit'];

                $node->fill([
                    'unit_cost' => $costs['cost_per_unit'],
                    'labor_hours' => $costs['total_labor_hours'],
                ]);

                // Un cambio de costo que no mueve la aguja de esta receta no
                // tiene por qué escribirla ni tocarle el updated_at.
                if ($node->isDirty()) {
                    $node->save();
                }
            }
        }
    }

    /**
     * Las recetas afectadas (las semillas y todos sus ancestros) ordenadas de
     * modo que ninguna aparezca antes que las sub-recetas de las que depende.
     *
     * @param  array<int, int>  $seedIds
     * @return array<int, int>
     */
    private function topologicalOrder(array $seedIds): array
    {
        /** @var array<int, true> */
        $affected = [];
        /** @var array<int, array<int, true>> hijo => padres */
        $parentsOf = [];

        $frontier = $seedIds;

        // Descubrimiento del conjunto afectado: una query por nivel del árbol,
        // no una por receta.
        while ($frontier !== []) {
            foreach ($frontier as $id) {
                $affected[$id] = true;
            }

            $edges = DB::table('recipe_subrecipe_lines')
                ->whereIn('child_recipe_id', $frontier)
                ->distinct()
                ->get(['child_recipe_id', 'recipe_id']);

            $next = [];

            foreach ($edges as $edge) {
                $child = (int) $edge->child_recipe_id;
                $parent = (int) $edge->recipe_id;

                $parentsOf[$child][$parent] = true;

                if (! isset($affected[$parent])) {
                    $next[$parent] = true;
                }
            }

            $frontier = array_keys($next);
        }

        // Kahn: una receta entra a la cola recién cuando ya salieron todas sus
        // sub-recetas afectadas.
        $pending = array_fill_keys(array_keys($affected), 0);

        foreach ($parentsOf as $parents) {
            foreach (array_keys($parents) as $parent) {
                if (isset($pending[$parent])) {
                    $pending[$parent]++;
                }
            }
        }

        $queue = array_keys(array_filter($pending, fn (int $count) => $count === 0));
        $order = [];

        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;

            foreach (array_keys($parentsOf[$id] ?? []) as $parent) {
                if (isset($pending[$parent]) && --$pending[$parent] === 0) {
                    $queue[] = $parent;
                }
            }
        }

        // Un ciclo —que el guard de isAncestor no debería dejar entrar— dejaría
        // recetas sin ordenar: se recalculan igual, al final, en vez de perderse.
        foreach ($pending as $id => $count) {
            if ($count > 0) {
                $order[] = $id;
            }
        }

        return $order;
    }
}

<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;
use App\Models\RecipePackagingLine;
use App\Models\RecipeSubrecipeLine;
use App\Models\SupplierProductLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reemplaza un ingrediente, un descartable o una sub-receta por otro en TODAS
 * las recetas que lo usan, para cuando un insumo queda descontinuado.
 *
 * Todo o nada: si alguna línea tiene una unidad incompatible con la del ítem
 * destino, la operación entera aborta sin tocar nada — un reemplazo parcial
 * seguido de la desactivación del ítem viejo dejaría esas recetas costeando
 * con un insumo dado de baja. preview() corre la misma validación sin
 * escribir, para que la UI la muestre antes de confirmar.
 *
 * Cuando la receta ya tenía una línea con el ítem destino, las dos se
 * fusionan (se suma la cantidad, convertida a la unidad de la línea
 * existente) en vez de dejar dos líneas del mismo ítem.
 *
 * Lo que NO se toca nunca: stock_movements (ledger inmutable), purchase_lines
 * (historial de facturas), stock_levels (cache derivado de StockService) e
 * ingredient_price_logs/packaging_price_logs (historial del ítem viejo).
 * Reescribirlos falsearía el pasado.
 */
class CatalogItemReplacer
{
    public function __construct(
        private readonly RecipeCostPropagator $propagator,
        private readonly UnitConverter $converter,
    ) {}

    /**
     * @return array{recipes: int, merged: int, links: int}
     */
    public function replaceIngredient(Ingredient $from, Ingredient $to, bool $deactivateSource, bool $migrateSupplierLinks): array
    {
        abort_unless($from->tenant_id === $to->tenant_id, 403, 'El ítem destino no pertenece al mismo tenant.');
        abort_if($from->id === $to->id, 422, 'Elegí un ítem distinto del que querés reemplazar.');

        $lines = RecipeIngredientLine::with('recipe')->where('ingredient_id', $from->id)->get();
        $this->assertCompatible($lines, $to->unit->value);

        return DB::transaction(function () use ($from, $to, $deactivateSource, $migrateSupplierLinks, $lines) {
            $recipeIds = $lines->pluck('recipe_id')->unique()->values()->all();
            $merged = 0;

            foreach ($lines as $line) {
                $merged += $this->replaceOrMergeLine(
                    RecipeIngredientLine::class,
                    $line,
                    'ingredient_id',
                    $to->id,
                );
            }

            $links = 0;
            if ($migrateSupplierLinks) {
                $links = SupplierProductLink::where('tenant_id', $from->tenant_id)
                    ->where('purchaseable_type', CatalogItemType::Ingredient->value)
                    ->where('purchaseable_id', $from->id)
                    ->update(['purchaseable_id' => $to->id]);
            }

            if ($deactivateSource) {
                $from->update(['active' => false]);
            }

            $this->propagator->propagateManyFrom($recipeIds);

            return ['recipes' => count($recipeIds), 'merged' => $merged, 'links' => $links];
        });
    }

    /**
     * @return array{recipes: int, merged: int}
     */
    public function replacePackaging(Packaging $from, Packaging $to, bool $deactivateSource): array
    {
        abort_unless($from->tenant_id === $to->tenant_id, 403, 'El ítem destino no pertenece al mismo tenant.');
        abort_if($from->id === $to->id, 422, 'Elegí un ítem distinto del que querés reemplazar.');

        // El packaging siempre se mide por unidad: no hay dimensión que validar,
        // a diferencia del ingrediente.
        $lines = RecipePackagingLine::with('recipe')->where('packaging_id', $from->id)->get();

        return DB::transaction(function () use ($from, $to, $deactivateSource, $lines) {
            $recipeIds = $lines->pluck('recipe_id')->unique()->values()->all();
            $merged = 0;

            foreach ($lines as $line) {
                $merged += $this->replaceOrMergeLine(
                    RecipePackagingLine::class,
                    $line,
                    'packaging_id',
                    $to->id,
                    hasUnit: false,
                );
            }

            if ($deactivateSource) {
                $from->update(['active' => false]);
            }

            $this->propagator->propagateManyFrom($recipeIds);

            return ['recipes' => count($recipeIds), 'merged' => $merged];
        });
    }

    /**
     * @return array{recipes: int, merged: int}
     */
    public function replaceSubrecipe(Recipe $from, Recipe $to, bool $deactivateSource): array
    {
        abort_unless($from->tenant_id === $to->tenant_id, 403, 'La sub-receta destino no pertenece al mismo tenant.');
        abort_if($from->id === $to->id, 422, 'Elegí una sub-receta distinta de la que querés reemplazar.');
        abort_unless($to->is_semi_elaborate, 422, 'El destino tiene que ser una sub-receta (semielaborado).');

        $lines = RecipeSubrecipeLine::with('recipe')->where('child_recipe_id', $from->id)->get();
        $this->assertCompatible($lines, $to->yield_unit->value);

        foreach ($lines as $line) {
            // isAncestor(childId, recipeId, tenant): ¿$to ya es ancestro de la receta
            // que lo va a usar como hijo? De ser así, agregarlo cerraría un ciclo —
            // misma validación que storeSubrecipeLine() al alta manual.
            abort_if(
                $this->propagator->isAncestor($to->id, $line->recipe_id, $to->tenant_id),
                422,
                "Reemplazar crearía un ciclo: «{$to->name}» usa a «{$line->recipe?->name}» como sub-receta.",
            );
        }

        return DB::transaction(function () use ($from, $to, $deactivateSource, $lines) {
            $recipeIds = $lines->pluck('recipe_id')->unique()->values()->all();
            $merged = 0;

            foreach ($lines as $line) {
                $merged += $this->replaceOrMergeLine(
                    RecipeSubrecipeLine::class,
                    $line,
                    'child_recipe_id',
                    $to->id,
                    quantityColumn: 'quantity_used',
                );
            }

            if ($deactivateSource) {
                $from->update(['active' => false]);
            }

            $this->propagator->propagateManyFrom($recipeIds);

            return ['recipes' => count($recipeIds), 'merged' => $merged];
        });
    }

    /**
     * @return array{recipes: array<int, string>, incompatible: array<int, string>, merges: int}
     */
    public function previewIngredient(Ingredient $from, Ingredient $to): array
    {
        $lines = RecipeIngredientLine::with('recipe')->where('ingredient_id', $from->id)->get();

        return $this->preview($lines, $to->unit->value, $to->id, RecipeIngredientLine::class, 'ingredient_id');
    }

    /**
     * @return array{recipes: array<int, string>, incompatible: array<int, string>, merges: int}
     */
    public function previewPackaging(Packaging $from, Packaging $to): array
    {
        $lines = RecipePackagingLine::with('recipe')->where('packaging_id', $from->id)->get();

        return $this->preview($lines, null, $to->id, RecipePackagingLine::class, 'packaging_id');
    }

    /**
     * @return array{recipes: array<int, string>, incompatible: array<int, string>, merges: int}
     */
    public function previewSubrecipe(Recipe $from, Recipe $to): array
    {
        $lines = RecipeSubrecipeLine::with('recipe')->where('child_recipe_id', $from->id)->get();

        return $this->preview($lines, $to->yield_unit->value, $to->id, RecipeSubrecipeLine::class, 'child_recipe_id');
    }

    /**
     * Aborta con 422 (sin escribir nada, se llama antes de la transacción) si
     * alguna línea tiene una unidad que no comparte dimensión con la del ítem
     * destino. Sólo aplica a ingredientes y sub-recetas: el packaging no
     * lleva unidad de línea (siempre es "unidad").
     *
     * @param  Collection<int, RecipeIngredientLine|RecipeSubrecipeLine>  $lines
     */
    private function assertCompatible(Collection $lines, string $targetUnitValue): void
    {
        $targetUnit = Unit::from($targetUnitValue);

        $incompatible = $lines->filter(fn ($line) => ! $this->converter->compatible($line->unit, $targetUnit));

        if ($incompatible->isNotEmpty()) {
            $names = $incompatible->map(fn ($line) => $line->recipe?->name ?? "receta #{$line->recipe_id}")->unique()->implode(', ');
            abort(422, "La unidad del ítem destino no es compatible con: {$names}.");
        }
    }

    /**
     * @param  Collection<int, RecipeIngredientLine|RecipePackagingLine|RecipeSubrecipeLine>  $lines
     * @return array{recipes: array<int, string>, incompatible: array<int, string>, merges: int}
     */
    private function preview(Collection $lines, ?string $targetUnitValue, int $toId, string $lineClass, string $foreignKey): array
    {
        $recipeNames = $lines->map(fn ($line) => $line->recipe?->name ?? "receta #{$line->recipe_id}")->unique()->values()->all();

        $incompatibleNames = [];
        if ($targetUnitValue !== null) {
            $targetUnit = Unit::from($targetUnitValue);
            $incompatibleNames = $lines
                ->filter(fn ($line) => ! $this->converter->compatible($line->unit, $targetUnit))
                ->map(fn ($line) => $line->recipe?->name ?? "receta #{$line->recipe_id}")
                ->unique()->values()->all();
        }

        $merges = $lineClass::whereIn('recipe_id', $lines->pluck('recipe_id'))
            ->where($foreignKey, $toId)
            ->count();

        return ['recipes' => $recipeNames, 'incompatible' => $incompatibleNames, 'merges' => $merges];
    }

    /**
     * Repunta una línea al ítem destino, o si la receta ya tenía una línea con
     * ese destino, suma la cantidad (convertida a la unidad de la línea
     * existente, cuando la línea lleva unidad) y borra la línea vieja.
     * Devuelve 1 si fusionó, 0 si no.
     */
    private function replaceOrMergeLine(
        string $lineClass,
        RecipeIngredientLine|RecipePackagingLine|RecipeSubrecipeLine $line,
        string $foreignKey,
        int $toId,
        string $quantityColumn = 'quantity',
        bool $hasUnit = true,
    ): int {
        $existing = $lineClass::where('recipe_id', $line->recipe_id)
            ->where($foreignKey, $toId)
            ->where('id', '!=', $line->id)
            ->first();

        if ($existing === null) {
            $line->update([$foreignKey => $toId]);

            return 0;
        }

        $converted = $hasUnit
            ? ($this->converter->convert((float) $line->{$quantityColumn}, $line->unit, $existing->unit) ?? (float) $line->{$quantityColumn})
            : (float) $line->{$quantityColumn};

        $existing->update([$quantityColumn => (float) $existing->{$quantityColumn} + $converted]);
        $line->delete();

        return 1;
    }
}

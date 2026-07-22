<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Models\Ingredient;
use App\Models\Packaging;
use App\Models\Recipe;
use Illuminate\Support\Collection;

/**
 * Explota el BOM de una receta a sus insumos base (ingredientes + descartables),
 * escalado por un factor. Las sub-recetas son phantom: se explotan recursivamente
 * hasta sus insumos reales y nunca aparecen como ítem consumido.
 *
 * Las cantidades vuelven ya en la unidad de cada ítem (la misma en la que
 * StockService espera los movimientos), agregadas por ítem: un insumo que aparece
 * en varias sub-recetas se suma una sola vez. La mano de obra se ignora (no es
 * insumo físico, no descuenta stock).
 */
class RecipeExploder
{
    public function __construct(private UnitConverter $converter) {}

    /**
     * @return Collection<int, array{type: CatalogItemType, item: Ingredient|Packaging, quantity: float}>
     */
    public function explode(Recipe $recipe, float $factor): Collection
    {
        $accumulator = [];
        $this->walk($recipe, $factor, $accumulator, []);

        return collect(array_values($accumulator));
    }

    /**
     * @param  array<string, array{type: CatalogItemType, item: Ingredient|Packaging, quantity: float}>  $accumulator
     * @param  array<int, bool>  $visited  ids de recetas ya recorridas en esta rama (guarda anti-ciclo)
     */
    private function walk(Recipe $recipe, float $factor, array &$accumulator, array $visited): void
    {
        if (isset($visited[$recipe->id])) {
            return;
        }
        $visited[$recipe->id] = true;

        $recipe->loadMissing([
            'ingredientLines.ingredient',
            'packagingLines.packaging',
            'subrecipeLines.childRecipe',
        ]);

        foreach ($recipe->ingredientLines as $line) {
            $converted = $this->converter->convert((float) $line->quantity, $line->unit, $line->ingredient->unit);
            if ($converted !== null) {
                $this->add($accumulator, CatalogItemType::Ingredient, $line->ingredient, $converted * $factor);
            }
        }

        foreach ($recipe->packagingLines as $line) {
            // Los descartables siempre se miden en unidades: sin conversión.
            $this->add($accumulator, CatalogItemType::Packaging, $line->packaging, (float) $line->quantity * $factor);
        }

        foreach ($recipe->subrecipeLines as $line) {
            $child = $line->childRecipe;
            $convertedYield = $this->converter->convert((float) $line->quantity_used, $line->unit, $child->yield_unit);

            if ($convertedYield !== null && (float) $child->yield_quantity > 0) {
                $childFactor = $factor * $convertedYield / (float) $child->yield_quantity;
                $this->walk($child, $childFactor, $accumulator, $visited);
            }
        }
    }

    /**
     * @param  array<string, array{type: CatalogItemType, item: Ingredient|Packaging, quantity: float}>  $accumulator
     */
    private function add(array &$accumulator, CatalogItemType $type, Ingredient|Packaging $item, float $quantity): void
    {
        $key = $type->value.':'.$item->id;

        if (isset($accumulator[$key])) {
            $accumulator[$key]['quantity'] += $quantity;

            return;
        }

        $accumulator[$key] = [
            'type' => $type,
            'item' => $item,
            'quantity' => $quantity,
        ];
    }
}

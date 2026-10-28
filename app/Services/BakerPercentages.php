<?php

namespace App\Services;

use App\Enums\Unit;
use App\Models\Recipe;
use App\Models\RecipeIngredientLine;

/**
 * Porcentaje panadero: la harina (suma de las líneas marcadas) es el 100% y
 * cada componente se expresa como % sobre ella. Peso directo, volumen como
 * 1 ml = 1 gr; lo que va en unidades queda fuera de la masa (grams/percent
 * null). Devuelve sólo arrays/escalares, apto para la vista de dompdf.
 */
class BakerPercentages
{
    /** Peso aproximado de un huevo cuando el insumo no tiene «peso por unidad» cargado. */
    private const DEFAULT_EGG_GRAMS = 60;

    public function __construct(private readonly UnitConverter $converter) {}

    /**
     * @return array{
     *     rows: list<array{kind: string, id: int, name: string, is_flour: bool, quantity: float, unit: string, grams: ?float, percent: ?float, scaled_quantity: float}>,
     *     flour_grams: float,
     *     dough_grams: float,
     *     total_percent: float,
     *     has_flour: bool,
     *     scale: float,
     * }
     */
    public function for(Recipe $recipe, ?float $flourGrams = null): array
    {
        $recipe->loadMissing([
            'ingredientLines.ingredient',
            'productLines.product',
            'subrecipeLines.childRecipe',
        ]);

        $rows = [];

        foreach ($recipe->ingredientLines as $line) {
            $subdivided = (bool) $line->ingredient->subdivisions;
            $rows[] = [
                'kind' => 'ingredient',
                'id' => $line->id,
                'name' => $line->ingredient->name,
                'is_flour' => (bool) $line->is_flour,
                'quantity' => (float) $line->quantity,
                'unit' => $subdivided ? ($line->ingredient->subdivision_label ?? 'u') : $line->unit->short(),
                'grams' => $this->ingredientGrams($line, $subdivided),
            ];
        }

        foreach ($recipe->productLines as $line) {
            $rows[] = [
                'kind' => 'product',
                'id' => $line->id,
                'name' => $line->product->name,
                'is_flour' => false,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit->short(),
                'grams' => $this->converter->toGrams((float) $line->quantity, $line->unit),
            ];
        }

        foreach ($recipe->subrecipeLines as $line) {
            $rows[] = [
                'kind' => 'subrecipe',
                'id' => $line->id,
                'name' => $line->childRecipe->name,
                'is_flour' => false,
                'quantity' => (float) $line->quantity_used,
                'unit' => $line->unit->short(),
                'grams' => $this->converter->toGrams((float) $line->quantity_used, $line->unit),
            ];
        }

        $flourTotal = 0.0;
        $doughTotal = 0.0;
        foreach ($rows as $row) {
            if ($row['grams'] === null) {
                continue;
            }
            $doughTotal += $row['grams'];
            if ($row['is_flour']) {
                $flourTotal += $row['grams'];
            }
        }

        $hasFlour = $flourTotal > 0;
        $scale = ($hasFlour && $flourGrams !== null && $flourGrams > 0) ? $flourGrams / $flourTotal : 1.0;

        $rows = array_map(function (array $row) use ($hasFlour, $flourTotal, $scale) {
            $row['percent'] = ($hasFlour && $row['grams'] !== null) ? $row['grams'] / $flourTotal * 100 : null;
            $row['scaled_quantity'] = $row['quantity'] * $scale;

            return $row;
        }, $rows);

        return [
            'rows' => $rows,
            'flour_grams' => $flourTotal,
            'dough_grams' => $doughTotal,
            'total_percent' => $hasFlour ? $doughTotal / $flourTotal * 100 : 0.0,
            'has_flour' => $hasFlour,
            'scale' => $scale,
        ];
    }

    /**
     * Gramos de una línea de ingrediente. Lo que va en unidades sólo pesa si el
     * ingrediente tiene cargado su peso por unidad (huevo ≈ 50 gr); si no, queda
     * fuera de la masa.
     */
    private function ingredientGrams(RecipeIngredientLine $line, bool $subdivided): ?float
    {
        $gramsPerUnit = $line->ingredient->grams_per_unit;

        if ($gramsPerUnit === null && $line->unit === Unit::Unidad && stripos($line->ingredient->name, 'huevo') !== false) {
            $gramsPerUnit = self::DEFAULT_EGG_GRAMS;
        }

        if ($line->unit === Unit::Unidad && $gramsPerUnit !== null) {
            return (float) $line->quantity * (float) $gramsPerUnit;
        }

        return $subdivided ? null : $this->converter->toGrams((float) $line->quantity, $line->unit);
    }
}

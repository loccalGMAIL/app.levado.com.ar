<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Carbon;

/**
 * Arma los datos de la receta imprimible (sin costos ni precios: es la hoja
 * que se le entrega a quien produce). Como ProductionOrderSheets, devuelve
 * sólo arrays/escalares -la vista se renderiza también para dompdf, y un
 * lazy load ahí adentro deja un PDF corrupto en vez de un error legible-.
 */
class RecipePrintSheet
{
    public function __construct(
        private readonly ReportLetterhead $letterhead,
        private readonly BakerPercentages $baker,
    ) {}

    /**
     * Con $flourGrams las cantidades de ingredientes y sub-recetas salen
     * escaladas a esa cantidad de harina.
     *
     * @return array{
     *     business: array{name: string, razon_social: ?string, cuit: ?string, condicion_iva: ?string, currency: string, logo: ?string},
     *     meta: array{name: string, description: ?string, yield: string, kind: string, generated_at: string},
     *     baker: array{has_flour: bool, scaled: bool, flour_grams: float},
     *     ingredients: list<array{name: string, quantity: float, unit: string, percent: ?float}>,
     *     subrecipes: list<array{name: string, quantity: float, unit: string, percent: ?float}>,
     *     packagings: list<array{name: string, quantity: float}>,
     *     labor: list<array{name: string, hours: float}>,
     * }
     */
    public function for(Recipe $recipe, ?float $flourGrams = null): array
    {
        $recipe->loadMissing([
            'tenant',
            'ingredientLines.ingredient',
            'productLines.product',
            'subrecipeLines.childRecipe',
            'packagingLines.packaging',
            'laborLines.laborType',
        ]);

        $baker = $this->baker->for($recipe, $flourGrams);
        $bakerRows = collect($baker['rows'])->groupBy('kind');
        $toRow = fn (array $row) => [
            'name' => $row['name'],
            'quantity' => $row['scaled_quantity'],
            'unit' => $row['unit'],
            'percent' => $row['percent'],
        ];

        return [
            'business' => $this->letterhead->for($recipe->tenant),
            'baker' => [
                'has_flour' => $baker['has_flour'],
                'scaled' => $baker['has_flour'] && $flourGrams !== null && $flourGrams > 0,
                'flour_grams' => $baker['flour_grams'] * $baker['scale'],
            ],
            'meta' => [
                'name' => $recipe->name,
                'description' => $recipe->description,
                'yield' => rtrim(rtrim(number_format((float) $recipe->yield_quantity, 3, ',', '.'), '0'), ',').' '.$recipe->yield_unit->short(),
                'kind' => $recipe->is_semi_elaborate ? 'Sub-receta' : 'Receta',
                'generated_at' => Carbon::now()->format('d/m/Y H:i'),
            ],
            'ingredients' => $bakerRows->get('ingredient', collect())
                ->concat($bakerRows->get('product', collect()))
                ->map($toRow)
                ->values()
                ->all(),
            'subrecipes' => $bakerRows->get('subrecipe', collect())
                ->map($toRow)
                ->values()
                ->all(),
            'packagings' => $recipe->packagingLines
                ->map(fn ($line) => [
                    'name' => $line->packaging->name,
                    'quantity' => (float) $line->quantity,
                ])
                ->all(),
            'labor' => $recipe->laborLines
                ->map(fn ($line) => [
                    'name' => $line->laborType->name,
                    'hours' => (float) $line->hours,
                ])
                ->all(),
        ];
    }
}

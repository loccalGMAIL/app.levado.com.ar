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
    public function __construct(private readonly ReportLetterhead $letterhead) {}

    /**
     * @return array{
     *     business: array{name: string, razon_social: ?string, cuit: ?string, condicion_iva: ?string, currency: string, logo: ?string},
     *     meta: array{name: string, description: ?string, yield: string, kind: string, generated_at: string},
     *     ingredients: list<array{name: string, quantity: float, unit: string}>,
     *     subrecipes: list<array{name: string, quantity: float, unit: string}>,
     *     packagings: list<array{name: string, quantity: float}>,
     *     labor: list<array{name: string, hours: float}>,
     * }
     */
    public function for(Recipe $recipe): array
    {
        $recipe->loadMissing([
            'tenant',
            'ingredientLines.ingredient',
            'subrecipeLines.childRecipe',
            'packagingLines.packaging',
            'laborLines.laborType',
        ]);

        return [
            'business' => $this->letterhead->for($recipe->tenant),
            'meta' => [
                'name' => $recipe->name,
                'description' => $recipe->description,
                'yield' => rtrim(rtrim(number_format((float) $recipe->yield_quantity, 3, ',', '.'), '0'), ',').' '.$recipe->yield_unit->short(),
                'kind' => $recipe->is_semi_elaborate ? 'Sub-receta' : 'Receta',
                'generated_at' => Carbon::now()->format('d/m/Y H:i'),
            ],
            'ingredients' => $recipe->ingredientLines
                ->map(fn ($line) => [
                    'name' => $line->ingredient->name,
                    'quantity' => (float) $line->quantity,
                    'unit' => $line->ingredient->subdivisions
                        ? ($line->ingredient->subdivision_label ?? 'u')
                        : $line->unit->short(),
                ])
                ->all(),
            'subrecipes' => $recipe->subrecipeLines
                ->map(fn ($line) => [
                    'name' => $line->childRecipe->name,
                    'quantity' => (float) $line->quantity_used,
                    'unit' => $line->unit->short(),
                ])
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

<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\ProductPrice;
use App\Models\Recipe;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Arma los datos de presentación de /recipes/{id}: el desglose de costos,
 * las líneas transformadas para Alpine.js, los catálogos de los modales de
 * alta (solo activos: acá no hay selects de edición de asociación) y las
 * listas de precios. Saca ~120 líneas de transformación del controller.
 *
 * @phpstan-type ShowData array<string, mixed>
 */
class RecipeShowViewModel
{
    public function __construct(
        private readonly RecipeCostCalculator $calculator,
        private readonly RecipeCostPropagator $propagator,
        private readonly UnitConverter $converter,
    ) {}

    /**
     * @return array<string, mixed> variables listas para view('recipes.show')
     */
    public function build(Recipe $recipe, Tenant $tenant): array
    {
        $recipe->loadMissing([
            'ingredientLines.ingredient.supplier',
            'productLines.product',
            'packagingLines.packaging',
            'laborLines.laborType',
            'subrecipeLines.childRecipe',
            'manufacturedProduct',
        ]);

        $costs = $this->calculator->calculate($recipe);

        // defaultPriceList() debe correr ANTES del get(): priceLists se renderiza
        // completa (selector de listas) y tiene que incluir la recién creada.
        $defaultPriceList = $tenant->defaultPriceList();
        $priceLists = $tenant->priceLists()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        // El precio de venta vive en el artículo elaborado (product_prices).
        $product = $recipe->manufacturedProduct;
        $priceRows = $product !== null
            ? ProductPrice::where('product_id', $product->id)
                ->whereIn('price_list_id', $priceLists->pluck('id'))
                ->get()
                ->keyBy('price_list_id')
            : collect();
        $allPrices = $priceRows->map(fn (ProductPrice $row) => $row->price !== null ? (float) $row->price : null)->toArray();
        $allPolicies = $priceRows->map(fn (ProductPrice $row) => $row->policyPayload())->toArray();

        return [
            'recipe' => $recipe,
            'manufacturedProduct' => $product,
            'defaultPriceList' => $defaultPriceList,
            'defaultPrice' => $allPrices[$defaultPriceList->id] ?? null,
            'priceLists' => $priceLists,
            'allPrices' => $allPrices,
            'allPolicies' => $allPolicies,
            'ingredientCost' => $costs['ingredient_cost'],
            'packagingCost' => $costs['packaging_cost'],
            'laborCost' => $costs['labor_cost'],
            'subrecipeCost' => $costs['subrecipe_cost'],
            'totalCost' => $costs['total_cost'],
            'costPerUnit' => $costs['cost_per_unit'],
            'overheadPerHour' => $tenant->overheadPerHour() ?? 0.0,
            'ingredients' => $tenant->ingredients()->active()->orderBy('name')->get(),
            'resaleProducts' => $tenant->products()->active()->where('type', ProductType::Resale->value)->orderBy('name')->get(),
            'packagings' => $tenant->packagings()->active()->orderBy('name')->get(),
            'laborTypes' => $tenant->laborTypes()->active()->orderBy('name')->get(),
            'ingredientLinesData' => $this->ingredientLinesData($recipe),
            'laborLinesData' => $this->laborLinesData($recipe),
            'packagingLinesData' => $this->packagingLinesData($recipe),
            'subrecipeLinesData' => $this->subrecipeLinesData($recipe),
            'semiElaborates' => $this->availableSemiElaborates($recipe, $tenant),
        ];
    }

    /**
     * Ingredientes y artículos de reventa usados como insumo, en una sola lista:
     * para la tabla son lo mismo. Cada fila trae sus propias URLs de guardado y
     * baja, así la vista no distingue de qué tabla viene.
     */
    private function ingredientLinesData(Recipe $recipe): Collection
    {
        $ingredientRows = $recipe->ingredientLines->map(function ($line) use ($recipe) {
            $ingredient = $line->ingredient;
            $subLabel = $ingredient->subdivisions ? ($ingredient->subdivision_label ?? 'u') : null;

            return [
                'key' => 'ing-'.$line->id,
                'kind' => 'ingredient',
                'updateUrl' => route('recipes.ingredient-lines.update', [$recipe, $line]),
                'destroyUrl' => route('recipes.ingredient-lines.destroy', [$recipe, $line]),
                'id' => $line->id,
                'name' => $ingredient->name,
                'code' => 'ING-'.str_pad((string) $ingredient->id, 3, '0', STR_PAD_LEFT),
                'supplier' => $ingredient->supplier?->name,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit->value,
                'unitLabel' => $subLabel ?? $line->unit->short(),
                'ingredientUnit' => $ingredient->unit->value,
                'costPerLineUnit' => $this->converter->convert(1.0, $line->unit, $ingredient->unit) * (float) $ingredient->cost_per_unit,
                'refCost' => (float) $ingredient->cost_per_unit,
                'refUnit' => $subLabel ?? $ingredient->unit->short(),
            ];
        });

        $productRows = $recipe->productLines->map(function ($line) use ($recipe) {
            $product = $line->product;

            return [
                'key' => 'prod-'.$line->id,
                'kind' => 'product',
                'updateUrl' => route('recipes.product-lines.update', [$recipe, $line]),
                'destroyUrl' => route('recipes.product-lines.destroy', [$recipe, $line]),
                'id' => $line->id,
                'name' => $product->name,
                'code' => 'ART-'.str_pad((string) $product->id, 3, '0', STR_PAD_LEFT),
                'supplier' => null,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit->value,
                'unitLabel' => $line->unit->short(),
                'ingredientUnit' => $product->unit->value,
                'costPerLineUnit' => $this->converter->convert(1.0, $line->unit, $product->unit) * (float) $product->cost_per_unit,
                'refCost' => (float) $product->cost_per_unit,
                'refUnit' => $product->unit->short(),
            ];
        });

        return $ingredientRows->concat($productRows)->values();
    }

    private function laborLinesData(Recipe $recipe): Collection
    {
        return $recipe->laborLines->map(fn ($line) => [
            'id' => $line->id,
            'name' => $line->laborType->name,
            'hours' => (float) $line->hours,
            'hourlyRate' => (float) $line->laborType->hourly_rate,
        ]);
    }

    private function packagingLinesData(Recipe $recipe): Collection
    {
        return $recipe->packagingLines->map(fn ($line) => [
            'id' => $line->id,
            'name' => $line->packaging->name,
            'quantity' => (float) $line->quantity,
            'costPerUnit' => (float) $line->packaging->cost_per_unit,
        ]);
    }

    private function subrecipeLinesData(Recipe $recipe): Collection
    {
        return $recipe->subrecipeLines->map(function ($line) {
            $child = $line->childRecipe;
            $childUnitCost = (float) ($child->unit_cost ?? 0);
            $factor = $this->converter->convert(1.0, $line->unit, $child->yield_unit);

            return [
                'id' => $line->id,
                'name' => $child->name,
                'quantity' => (float) $line->quantity_used,
                'unit' => $line->unit->value,
                'unitLabel' => $line->unit->short(),
                'unitCost' => $childUnitCost,
                'costPerLineUnit' => ($factor !== null) ? $factor * $childUnitCost : 0.0,
                'childYieldUnit' => $child->yield_unit->short(),
            ];
        });
    }

    /**
     * Sub-recetas activas que pueden agregarse sin crear un ciclo en el árbol.
     */
    private function availableSemiElaborates(Recipe $recipe, Tenant $tenant): Collection
    {
        // Cierre de ancestros calculado una sola vez (no candidata por candidata)
        // y el descarte ocurre en SQL, no filtrando una colección ya hidratada.
        $ancestorIds = $this->propagator->ancestorIdsOf($recipe->id, $tenant->id);

        return $tenant->recipes()
            ->where('is_semi_elaborate', true)
            ->active()
            ->where('id', '!=', $recipe->id)
            ->whereNotIn('id', $ancestorIds)
            ->orderBy('name')
            ->get();
    }
}

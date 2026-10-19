<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\CostLogSource;
use App\Enums\ProductType;
use App\Enums\Unit;
use App\Models\Ingredient;
use App\Models\IngredientPriceLog;
use App\Models\Product;
use App\Models\ProductCostLog;
use App\Models\PurchaseLine;
use App\Models\RecipeIngredientLine;
use App\Models\RecipeProductLine;
use App\Models\StockLevel;
use App\Models\SupplierProductLink;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Convierte un Insumo en un Producto de reventa migrando su historial completo
 * (compras, stock, costo, recetas) en vez de dejar dos ítems separados. Es la
 * excepción deliberada a la regla que sigue CatalogItemReplacer (nunca tocar el
 * ledger): acá el objetivo es unificar la identidad del ítem, no reemplazarlo por
 * otro.
 *
 * Puede crear el artículo nuevo o unificar con uno de reventa que ya existe
 * ($target): el caso de un producto que se vende y se usa como insumo y quedó
 * cargado dos veces. Las recetas que usaban el insumo pasan a usar el artículo
 * (recipe_product_lines), así que ya no hay nada que bloquee la conversión.
 *
 * stock_movements es un ledger inmutable (StockMovement::booted() tira una
 * excepción ante cualquier update/delete vía Eloquent), así que esa tabla se
 * reapunta con el query builder puro, saltando el modelo a propósito.
 */
class IngredientToProductConverter
{
    public function __construct(
        private readonly ProductCodeAssigner $codeAssigner,
        private readonly NotificationService $notifications,
        private readonly UnitConverter $converter,
        private readonly RecipeCostPropagator $propagator,
        private readonly ArticlePriceRecalculator $priceRecalculator,
    ) {}

    public function convert(Ingredient $ingredient, ?int $categoryId, ?Product $target = null): Product
    {
        $this->assertConvertible($ingredient, $target);

        $tenant = Tenant::findOrFail($ingredient->tenant_id);

        $result = DB::transaction(function () use ($ingredient, $categoryId, $tenant, $target) {
            $product = $target ?? $this->createProduct($ingredient, $categoryId);

            // Un artículo existente sin costo toma el del insumo; con costo propio,
            // el vigente manda (el historial del insumo se suma igual).
            if ($target !== null && (float) $target->cost_per_unit <= 0 && (float) $ingredient->cost_per_unit > 0) {
                $target->update(['cost_per_unit' => $ingredient->cost_per_unit]);
            }

            $this->migrateCostHistory($ingredient, $product);

            PurchaseLine::where('purchaseable_type', CatalogItemType::Ingredient->value)
                ->where('purchaseable_id', $ingredient->id)
                ->update(['purchaseable_type' => CatalogItemType::Product->value, 'purchaseable_id' => $product->id]);

            $this->migrateSupplierLinks($ingredient, $product);
            $this->migrateStockLevels($ingredient, $product);

            // Bypass deliberado de StockMovement::booted() (ver docblock de la clase):
            // el query builder no dispara eventos de Eloquent, así que el guard de
            // inmutabilidad no salta. reference_type/reference_id no se tocan: siguen
            // apuntando a la misma purchase_line, ya reapuntada arriba.
            DB::table('stock_movements')
                ->where('stockable_type', CatalogItemType::Ingredient->value)
                ->where('stockable_id', $ingredient->id)
                ->update(['stockable_type' => CatalogItemType::Product->value, 'stockable_id' => $product->id]);

            $recipeIds = $this->migrateRecipeLines($ingredient, $product);

            $this->notifications->resolveByDedupeKey($tenant, "stale_cost:ingredient:{$ingredient->id}");

            $ingredient->update(['active' => false, 'converted_to_product_id' => $product->id]);

            return [$product, $recipeIds];
        });

        [$product, $recipeIds] = $result;

        // Fuera de la transacción: recalcula recetas ancestro y precios con política.
        $this->propagator->propagateManyFrom($recipeIds);
        $this->priceRecalculator->recompute($product);

        return $product;
    }

    /**
     * Qué se movería al unificar, sin escribir nada. Para --dry-run y para el aviso
     * del modal.
     *
     * @return array{recipes: list<string>, purchase_lines: int, supplier_links: int, stock_quantity: float, units_match: bool}
     */
    public function preview(Ingredient $ingredient, ?Product $target = null): array
    {
        return [
            'recipes' => $this->recipeNamesUsing($ingredient),
            'purchase_lines' => PurchaseLine::where('purchaseable_type', CatalogItemType::Ingredient->value)
                ->where('purchaseable_id', $ingredient->id)->count(),
            'supplier_links' => SupplierProductLink::where('purchaseable_type', CatalogItemType::Ingredient->value)
                ->where('purchaseable_id', $ingredient->id)->count(),
            'stock_quantity' => (float) StockLevel::where('stockable_type', CatalogItemType::Ingredient->value)
                ->where('stockable_id', $ingredient->id)->sum('quantity'),
            'units_match' => $target === null || $target->unit === $ingredient->unit,
        ];
    }

    /**
     * @return list<string>
     */
    public function recipeNamesUsing(Ingredient $ingredient): array
    {
        return DB::table('recipe_ingredient_lines')
            ->join('recipes', 'recipes.id', '=', 'recipe_ingredient_lines.recipe_id')
            ->where('recipe_ingredient_lines.ingredient_id', $ingredient->id)
            ->distinct()
            ->orderBy('recipes.name')
            ->pluck('recipes.name')
            ->all();
    }

    private function assertConvertible(Ingredient $ingredient, ?Product $target): void
    {
        abort_if(
            $ingredient->converted_to_product_id !== null,
            422,
            "Ya fue convertido a «{$ingredient->convertedToProduct?->name}».",
        );
        abort_unless($ingredient->active, 422, 'El insumo está inactivo.');

        if ($target === null) {
            return;
        }

        abort_unless($target->tenant_id === $ingredient->tenant_id, 403, 'El artículo no pertenece al mismo negocio.');
        abort_unless($target->isResale(), 422, 'Solo se puede unificar con un artículo de reventa.');
        // Las cantidades del ledger y de las recetas no se convierten: se reapuntan tal cual.
        abort_unless(
            $target->unit === $ingredient->unit,
            422,
            "El insumo está en {$ingredient->unit->short()} y el artículo en {$target->unit->short()}: no se pueden unificar con unidades distintas.",
        );
    }

    private function createProduct(Ingredient $ingredient, ?int $categoryId): Product
    {
        $product = Product::create([
            'tenant_id' => $ingredient->tenant_id,
            'name' => $ingredient->name,
            'type' => ProductType::Resale,
            'unit' => $ingredient->unit,
            'cost_per_unit' => $ingredient->cost_per_unit,
            'product_category_id' => $categoryId,
            'active' => true,
        ]);
        $this->codeAssigner->assignIfMissing($product);

        return $product;
    }

    /**
     * Los vínculos de proveedor pasan al artículo. Un insumo en `u` con
     * subdivisiones guarda el stock en sub-unidades; el artículo no tiene
     * subdivisiones fijas, así que ese divisor queda en el vínculo (pkg_qty) para
     * que la próxima compra del bulto siga entrando en sub-unidades.
     */
    private function migrateSupplierLinks(Ingredient $ingredient, Product $product): void
    {
        $links = SupplierProductLink::where('purchaseable_type', CatalogItemType::Ingredient->value)
            ->where('purchaseable_id', $ingredient->id)
            ->get();

        $keepsSubdivisionDivisor = $ingredient->unit === Unit::Unidad && (int) $ingredient->subdivisions > 0;

        foreach ($links as $link) {
            $link->purchaseable_type = CatalogItemType::Product->value;
            $link->purchaseable_id = $product->id;

            if ($keepsSubdivisionDivisor && $link->pkg_qty === null) {
                $link->pkg_qty = $ingredient->subdivisions;
            }

            $link->save();
        }
    }

    /**
     * Si el artículo ya tiene stock en la misma sucursal, las cantidades se suman
     * (costo promedio ponderado); si no, el nivel del insumo se reapunta tal cual.
     */
    private function migrateStockLevels(Ingredient $ingredient, Product $product): void
    {
        $levels = StockLevel::where('stockable_type', CatalogItemType::Ingredient->value)
            ->where('stockable_id', $ingredient->id)
            ->get();

        foreach ($levels as $level) {
            $existing = StockLevel::where('stockable_type', CatalogItemType::Product->value)
                ->where('stockable_id', $product->id)
                ->where('location_id', $level->location_id)
                ->first();

            if ($existing === null) {
                $level->update(['stockable_type' => CatalogItemType::Product->value, 'stockable_id' => $product->id]);

                continue;
            }

            $existingQty = (float) $existing->quantity;
            $incomingQty = (float) $level->quantity;
            $totalQty = $existingQty + $incomingQty;

            $unitCost = ($existingQty > 0 && $incomingQty > 0)
                ? ($existingQty * (float) $existing->unit_cost + $incomingQty * (float) $level->unit_cost) / $totalQty
                : ($existingQty > 0 ? (float) $existing->unit_cost : (float) $level->unit_cost);

            $existing->update([
                'quantity' => $totalQty,
                'unit_cost' => round($unitCost, 4),
                'min_quantity' => $existing->min_quantity ?? $level->min_quantity,
            ]);
            $level->delete();
        }
    }

    /**
     * Las líneas de receta del insumo pasan a ser líneas del artículo. Si la receta
     * ya tenía una línea del artículo, se fusionan sumando la cantidad (convertida a
     * la unidad de la línea existente).
     *
     * @return list<int> ids de las recetas tocadas
     */
    private function migrateRecipeLines(Ingredient $ingredient, Product $product): array
    {
        $recipeIds = [];

        foreach (RecipeIngredientLine::where('ingredient_id', $ingredient->id)->get() as $line) {
            $existing = RecipeProductLine::where('recipe_id', $line->recipe_id)
                ->where('product_id', $product->id)
                ->first();

            if ($existing === null) {
                RecipeProductLine::create([
                    'recipe_id' => $line->recipe_id,
                    'product_id' => $product->id,
                    'quantity' => $line->quantity,
                    'unit' => $line->unit,
                ]);
            } else {
                $added = $this->converter->convert((float) $line->quantity, $line->unit, $existing->unit);
                abort_if($added === null, 422, 'Unidades de receta incompatibles al fusionar líneas.');
                $existing->update(['quantity' => (float) $existing->quantity + $added]);
            }

            $line->delete();
            $recipeIds[] = (int) $line->recipe_id;
        }

        return array_values(array_unique($recipeIds));
    }

    /**
     * Reconstruye product_cost_logs a partir de dos fuentes, porque
     * ingredient_price_logs no distingue compra de carga manual (no tiene
     * columna `source` ni `purchase_line_id`):
     *
     * 1. Compras reales: una entrada por cada purchase_line aplicada y no
     *    bonificada, con el costo que efectivamente quedó en stock_movements
     *    (más confiable, y de acá sale el link a la factura).
     * 2. El resto de ingredient_price_logs que no coincide con ninguna entrada
     *    ya reconstruida en el paso 1 se asume manual.
     */
    private function migrateCostHistory(Ingredient $ingredient, Product $product): void
    {
        $purchaseLines = PurchaseLine::where('purchaseable_type', CatalogItemType::Ingredient->value)
            ->where('purchaseable_id', $ingredient->id)
            ->where('is_bonus', false)
            ->whereNotNull('cost_applied_at')
            ->whereNull('excluded_at')
            ->get();

        $reconstructed = [];

        foreach ($purchaseLines as $line) {
            $movement = DB::table('stock_movements')
                ->where('reference_type', 'purchase_line')
                ->where('reference_id', $line->id)
                ->first();

            if ($movement === null) {
                continue;
            }

            ProductCostLog::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'purchase_line_id' => $line->id,
                'cost_per_unit' => $movement->unit_cost,
                'source' => CostLogSource::Purchase,
                'recorded_at' => $line->cost_applied_at,
            ]);

            $reconstructed[] = [round((float) $movement->unit_cost, 4), Carbon::parse($line->cost_applied_at)];
        }

        $priceLogs = IngredientPriceLog::where('ingredient_id', $ingredient->id)->get();

        foreach ($priceLogs as $log) {
            $cost = round((float) $log->cost_per_unit, 4);
            $recordedAt = Carbon::parse($log->recorded_at);

            $isFromPurchase = collect($reconstructed)->contains(
                fn ($entry) => abs($entry[0] - $cost) < 0.0001 && abs($entry[1]->diffInSeconds($recordedAt)) <= 2,
            );

            if ($isFromPurchase) {
                continue;
            }

            ProductCostLog::create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'purchase_line_id' => null,
                'cost_per_unit' => $log->cost_per_unit,
                'source' => CostLogSource::Manual,
                'recorded_at' => $log->recorded_at,
            ]);
        }
    }
}

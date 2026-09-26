<?php

namespace App\Services;

use App\Enums\CatalogItemType;
use App\Enums\CostLogSource;
use App\Enums\ProductType;
use App\Models\Ingredient;
use App\Models\IngredientPriceLog;
use App\Models\Product;
use App\Models\ProductCostLog;
use App\Models\PurchaseLine;
use App\Models\StockLevel;
use App\Models\SupplierProductLink;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Convierte un Insumo en un Producto de reventa migrando su historial completo
 * (compras, stock, costo) en vez de dejar dos ítems separados. Es la excepción
 * deliberada a la regla que sigue CatalogItemReplacer (nunca tocar el ledger):
 * acá el objetivo es unificar la identidad del ítem, no reemplazarlo por otro.
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
    ) {}

    public function convert(Ingredient $ingredient, ?int $categoryId): Product
    {
        abort_if(
            $ingredient->converted_to_product_id !== null,
            422,
            "Ya fue convertido a «{$ingredient->convertedToProduct?->name}».",
        );
        abort_unless($ingredient->active, 422, 'El insumo está inactivo.');

        $recipeNames = DB::table('recipe_ingredient_lines')
            ->join('recipes', 'recipes.id', '=', 'recipe_ingredient_lines.recipe_id')
            ->where('recipe_ingredient_lines.ingredient_id', $ingredient->id)
            ->distinct()
            ->pluck('recipes.name')
            ->all();

        abort_if(
            ! empty($recipeNames),
            422,
            'Este insumo se usa en: '.implode(', ', $recipeNames).'. Reemplazalo en esas recetas antes de convertirlo.',
        );

        $tenant = Tenant::findOrFail($ingredient->tenant_id);

        return DB::transaction(function () use ($ingredient, $categoryId, $tenant) {
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

            $this->migrateCostHistory($ingredient, $product);

            PurchaseLine::where('purchaseable_type', CatalogItemType::Ingredient->value)
                ->where('purchaseable_id', $ingredient->id)
                ->update(['purchaseable_type' => CatalogItemType::Product->value, 'purchaseable_id' => $product->id]);

            SupplierProductLink::where('purchaseable_type', CatalogItemType::Ingredient->value)
                ->where('purchaseable_id', $ingredient->id)
                ->update(['purchaseable_type' => CatalogItemType::Product->value, 'purchaseable_id' => $product->id]);

            StockLevel::where('stockable_type', CatalogItemType::Ingredient->value)
                ->where('stockable_id', $ingredient->id)
                ->update(['stockable_type' => CatalogItemType::Product->value, 'stockable_id' => $product->id]);

            // Bypass deliberado de StockMovement::booted() (ver docblock de la clase):
            // el query builder no dispara eventos de Eloquent, así que el guard de
            // inmutabilidad no salta. reference_type/reference_id no se tocan: siguen
            // apuntando a la misma purchase_line, ya reapuntada arriba.
            DB::table('stock_movements')
                ->where('stockable_type', CatalogItemType::Ingredient->value)
                ->where('stockable_id', $ingredient->id)
                ->update(['stockable_type' => CatalogItemType::Product->value, 'stockable_id' => $product->id]);

            $this->notifications->resolveByDedupeKey($tenant, "stale_cost:ingredient:{$ingredient->id}");

            $ingredient->update(['active' => false, 'converted_to_product_id' => $product->id]);

            return $product;
        });
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

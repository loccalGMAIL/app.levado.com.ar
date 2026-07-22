<?php

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Recipe;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Crea un Producto elaborado (type=manufactured) por cada receta vendible que
 * todavía no lo tenga, para poder producirla desde el módulo de Producción.
 *
 * El producto solo enlaza la receta: unit = rendimiento de la receta,
 * cost_per_unit = null (se deriva de recipe.unit_cost). El precio de venta NO se
 * migra — sigue viviendo en recipe_prices (decisión del módulo Artículos).
 *
 * Idempotente: saltea recetas que ya tienen su producto elaborado y las
 * semielaboradas (phantom, no vendibles).
 */
class CreateProductsFromRecipes extends Command
{
    protected $signature = 'products:from-recipes
                            {--all : Incluir también recetas finales activas sin precio de venta}
                            {--tenant= : Limitar a un tenant por id}
                            {--category= : Asignar los productos creados a esta categoría (se crea por negocio si no existe)}
                            {--dry-run : Mostrar qué se crearía sin escribir nada}
                            {--force : Crear sin pedir confirmación}';

    protected $description = 'Crea un Producto elaborado por cada receta vendible con precio que aún no lo tenga, para producirla.';

    public function handle(): int
    {
        $includeUnpriced = (bool) $this->option('all');
        $tenantId = $this->option('tenant');
        $categoryName = $this->option('category');

        $recipes = Recipe::query()
            ->with('tenant')
            ->where('is_semi_elaborate', false)
            ->where('active', true)
            // Sin un producto elaborado ya asociado a la receta.
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.recipe_id', 'recipes.id')
                    ->where('products.type', ProductType::Manufactured->value);
            })
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            // Por defecto solo recetas con al menos un precio de venta cargado.
            ->when(! $includeUnpriced, fn ($query) => $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('recipe_prices')
                    ->whereColumn('recipe_prices.recipe_id', 'recipes.id');
            }))
            ->orderBy('tenant_id')
            ->orderBy('name')
            ->get();

        if ($recipes->isEmpty()) {
            info('No hay recetas pendientes de convertir en productos elaborados.');

            return self::SUCCESS;
        }

        $rows = $recipes->map(fn (Recipe $recipe) => [
            'Negocio' => $recipe->tenant?->name ?? $recipe->tenant_id,
            'Receta' => $recipe->name,
            'Rinde' => $this->trimNumber((float) $recipe->yield_quantity).' '.$recipe->yield_unit->short(),
            'Costo/u' => $recipe->unit_cost !== null ? '$ '.number_format((float) $recipe->unit_cost, 2) : '—',
        ])->all();

        warning("Se crearán {$recipes->count()} producto(s) elaborado(s) a partir de estas recetas:");
        $this->newLine();
        table(['Negocio', 'Receta', 'Rinde', 'Costo/u'], $rows);
        $this->newLine();
        info('Cada producto se enlaza a su receta (unidad = rendimiento, costo derivado). El precio de venta sigue en la receta.');
        if ($categoryName) {
            info("Se asignarán a la categoría «{$categoryName}» (se crea por negocio si no existe).");
        }

        if ($this->option('dry-run')) {
            info('Dry-run: no se creó nada.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm('¿Confirmar la creación de estos productos?', default: false)) {
            info('Operación cancelada.');

            return self::SUCCESS;
        }

        $categoryByTenant = []; // cache tenant_id → category_id (la categoría es por negocio)

        $created = 0;
        foreach ($recipes as $recipe) {
            $categoryId = null;
            if ($categoryName) {
                $categoryId = $categoryByTenant[$recipe->tenant_id] ??= ProductCategory::firstOrCreate(
                    ['tenant_id' => $recipe->tenant_id, 'name' => $categoryName],
                    ['producible' => true],
                )->id;
            }

            Product::create([
                'tenant_id' => $recipe->tenant_id,
                'name' => $recipe->name,
                'type' => ProductType::Manufactured->value,
                'recipe_id' => $recipe->id,
                'product_category_id' => $categoryId,
                'unit' => $recipe->yield_unit->value,
                'cost_per_unit' => null,
                'active' => true,
            ]);
            $created++;
        }

        info("Se crearon {$created} producto(s) elaborado(s). Ya se pueden producir desde Producción.");

        return self::SUCCESS;
    }

    /** Formatea una cantidad sin ceros decimales sobrantes (12.000 → 12, 1.500 → 1.5). */
    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}

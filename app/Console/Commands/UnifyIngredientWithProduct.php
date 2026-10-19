<?php

namespace App\Console\Commands;

use App\Enums\CatalogItemType;
use App\Enums\ProductType;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\PurchaseLine;
use App\Models\RecipeIngredientLine;
use App\Models\StockLevel;
use App\Services\IngredientToProductConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class UnifyIngredientWithProduct extends Command
{
    protected $signature = 'catalog:unify-ingredient
                            {ingredient? : ID del insumo a unificar}
                            {product? : ID del artículo de reventa destino}
                            {--list : Lista los pares insumo/artículo con el mismo nombre, sin escribir nada}
                            {--tenant= : Limita --list a un negocio (id)}
                            {--dry-run : Muestra qué se movería, sin escribir nada}
                            {--force : No pide confirmación}';

    protected $description = 'Unifica un insumo con un artículo de reventa duplicado (se vende y se usa como insumo), migrando compras, stock, costo y recetas.';

    public function __construct(private readonly IngredientToProductConverter $converter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listCandidates();
        }

        $ingredientId = $this->argument('ingredient');
        $productId = $this->argument('product');

        if ($ingredientId === null || $productId === null) {
            error('Indicá el insumo y el artículo (ids), o usá --list para ver los candidatos.');

            return self::INVALID;
        }

        $ingredient = Ingredient::find((int) $ingredientId);
        $product = Product::find((int) $productId);

        if ($ingredient === null || $product === null) {
            error('No existe el insumo o el artículo indicado.');

            return self::FAILURE;
        }

        try {
            $preview = $this->converter->preview($ingredient, $product);
        } catch (HttpException $e) {
            error($e->getMessage());

            return self::FAILURE;
        }

        $this->renderPreview($ingredient, $product, $preview);

        if ($this->option('dry-run')) {
            info('Dry-run: no se escribió nada.');

            return self::SUCCESS;
        }

        if (! $preview['units_match']) {
            error('Las unidades del insumo y del artículo no coinciden: no se puede unificar.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm('¿Unificar? El insumo queda desactivado y esto no se puede deshacer.', default: false)) {
            warning('Cancelado.');

            return self::SUCCESS;
        }

        try {
            $this->converter->convert($ingredient, null, $product);
        } catch (HttpException $e) {
            error($e->getMessage());

            return self::FAILURE;
        }

        info("Listo: «{$ingredient->name}» ahora vive en el artículo #{$product->id}.");

        return self::SUCCESS;
    }

    private function listCandidates(): int
    {
        $products = Product::query()
            ->where('type', ProductType::Resale->value)
            ->where('active', true)
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->where('tenant_id', (int) $tenant))
            ->get()
            ->groupBy(fn (Product $product) => $product->tenant_id.'|'.$this->normalize($product->name));

        $rows = [];

        Ingredient::query()
            ->notConverted()
            ->where('active', true)
            ->when($this->option('tenant'), fn ($query, $tenant) => $query->where('tenant_id', (int) $tenant))
            ->orderBy('tenant_id')
            ->orderBy('name')
            ->each(function (Ingredient $ingredient) use ($products, &$rows) {
                foreach ($products->get($ingredient->tenant_id.'|'.$this->normalize($ingredient->name), []) as $product) {
                    $rows[] = [
                        (string) $ingredient->tenant_id,
                        "#{$ingredient->id}",
                        $ingredient->name,
                        $ingredient->unit->short(),
                        (string) RecipeIngredientLine::where('ingredient_id', $ingredient->id)->distinct()->count('recipe_id'),
                        (string) PurchaseLine::where('purchaseable_type', CatalogItemType::Ingredient->value)->where('purchaseable_id', $ingredient->id)->count(),
                        $this->stockOf(CatalogItemType::Ingredient, $ingredient->id),
                        "#{$product->id}",
                        $product->unit->short(),
                        (string) PurchaseLine::where('purchaseable_type', CatalogItemType::Product->value)->where('purchaseable_id', $product->id)->count(),
                        $this->stockOf(CatalogItemType::Product, $product->id),
                        $product->unit === $ingredient->unit ? 'sí' : 'NO (unidades distintas)',
                    ];
                }
            });

        if ($rows === []) {
            info('No hay insumos con un artículo de reventa del mismo nombre.');

            return self::SUCCESS;
        }

        warning(count($rows).' par(es) insumo/artículo con el mismo nombre.');
        table(
            ['Negocio', 'Insumo', 'Nombre', 'U.', 'Recetas', 'Compras', 'Stock', 'Artículo', 'U.', 'Compras', 'Stock', 'Unificable'],
            $rows,
        );
        info('Para unificar un par: php artisan catalog:unify-ingredient {insumo} {artículo} [--dry-run]');

        return self::SUCCESS;
    }

    /**
     * @param  array{recipes: list<string>, purchase_lines: int, supplier_links: int, stock_quantity: float, units_match: bool}  $preview
     */
    private function renderPreview(Ingredient $ingredient, Product $product, array $preview): void
    {
        info("Insumo #{$ingredient->id} «{$ingredient->name}» → Artículo #{$product->id} «{$product->name}»");
        table(['Se mueve', 'Cantidad'], [
            ['Recetas', $preview['recipes'] === [] ? '0' : count($preview['recipes']).': '.implode(', ', $preview['recipes'])],
            ['Líneas de compra', (string) $preview['purchase_lines']],
            ['Vínculos de proveedor', (string) $preview['supplier_links']],
            ['Stock (se suma al del artículo)', rtrim(rtrim(number_format($preview['stock_quantity'], 4, '.', ''), '0'), '.').' '.$ingredient->unit->short()],
            ['Unidades coinciden', $preview['units_match'] ? 'sí' : 'NO'],
        ]);
    }

    private function stockOf(CatalogItemType $type, int $id): string
    {
        $quantity = (float) StockLevel::where('stockable_type', $type->value)->where('stockable_id', $id)->sum('quantity');

        return rtrim(rtrim(number_format($quantity, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function normalize(string $name): string
    {
        return (string) Str::of(Str::ascii($name))->lower()->squish();
    }
}

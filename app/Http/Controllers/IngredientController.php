<?php

namespace App\Http\Controllers;

use App\Enums\CatalogItemType;
use App\Enums\Unit;
use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\Ingredient;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\AdminActivityRecorder;
use App\Services\IngredientToProductConverter;
use App\Services\RecipeCostPropagator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IngredientController extends Controller
{
    public function __construct(
        private readonly AdminActivityRecorder $recorder,
        private readonly RecipeCostPropagator $propagator,
        private readonly IngredientToProductConverter $converter,
    ) {}

    public function index(): View
    {
        $tenant = app(Tenant::class);
        $locationId = $tenant->defaultLocation()->id;

        $ingredients = $tenant->ingredients()
            ->with('supplier')
            ->notConverted()
            ->when(request('search'), function ($q, $search) {
                $pattern = $this->containsPattern($search);

                return $q->where('name', 'like', $pattern);
            })
            ->when(request('status') === 'active', fn ($q) => $q->active())
            ->when(request('status') === 'inactive', fn ($q) => $q->where('active', false))
            ->tap(fn (Builder $q) => $this->sortListing($q, [
                'name' => 'name',
                'brand' => 'brand',
                'supplier' => Supplier::select('name')->whereColumn('suppliers.id', 'ingredients.supplier_id'),
                'unit' => 'unit',
                'cost_per_package' => 'cost_per_package',
                'cost_per_unit' => 'cost_per_unit',
                'stock' => fn (Builder $q, string $dir) => StockLevel::orderCatalogByQuantity($q, 'ingredients', CatalogItemType::Ingredient, $locationId, $dir),
            ], fn (Builder $q) => $q->orderBy('name'), 'ingredients.id'))
            ->paginate($this->perPage())
            ->withQueryString();
        // Todos, no sólo los activos: el modal de alta filtra a activos, pero el de edición
        // debe poder mostrar un proveedor ya dado de baja o el select caería en «Ninguno»
        // y guardar borraría el proveedor en silencio.
        $suppliers = $tenant->suppliers()->orderBy('name')->get();

        $stockLevels = StockLevel::query()
            ->where('tenant_id', $tenant->id)
            ->where('location_id', $locationId)
            ->where('stockable_type', CatalogItemType::Ingredient->value)
            ->whereIn('stockable_id', $ingredients->pluck('id'))
            ->get()
            ->keyBy('stockable_id');

        // Para el select opcional de categoría en el modal "Convertir a producto".
        $productCategories = $tenant->productCategories()->orderBy('name')->get();

        return view('ingredients.index', compact('ingredients', 'suppliers', 'stockLevels', 'productCategories'));
    }

    public function store(StoreIngredientRequest $request): RedirectResponse
    {
        $tenant = app(Tenant::class);
        $data = $request->validated();

        if (($data['unit'] ?? '') !== Unit::Unidad->value) {
            $data['grams_per_unit'] = null;
        }

        if (! empty($data['subdivisions']) && ($data['unit'] ?? '') === Unit::Unidad->value) {
            $data['cost_per_package'] = $data['cost_per_unit'];
            $data['cost_per_unit'] = $data['cost_per_unit'] / $data['subdivisions'];
        }

        $ingredient = $tenant->ingredients()->create($data);

        $ingredient->priceLogs()->create([
            'cost_per_unit' => $ingredient->cost_per_unit,
            'recorded_at' => now(),
        ]);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'ingredient',
            targetId: $ingredient->id,
            action: 'ingredient.created',
            payload: ['name' => $ingredient->name],
            tenantId: $tenant->id,
        );

        return back(fallback: route('ingredients.index'))->with('status', 'Ingrediente creado.');
    }

    public function update(UpdateIngredientRequest $request, Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('update', $ingredient);

        $data = $request->validated();

        if ($data['unit'] !== $ingredient->unit->value && $this->unitIsLocked($ingredient)) {
            return back()->withErrors([
                'unit' => 'No podés cambiar la unidad de un insumo que ya tiene recetas, compras o movimientos de stock: '
                    .'sus cantidades y su costo quedarían en la unidad anterior. Creá un insumo nuevo y usá «Reemplazar».',
            ])->withInput();
        }

        if ($data['unit'] !== Unit::Unidad->value) {
            $data['grams_per_unit'] = null;
        }

        if (! empty($data['subdivisions']) && $data['unit'] === Unit::Unidad->value) {
            $data['cost_per_package'] = $data['cost_per_unit'];
            $data['cost_per_unit'] = $data['cost_per_unit'] / $data['subdivisions'];
        } else {
            $data['cost_per_package'] = null;
        }

        // A la precisión de la columna (decimal 4): el costo por sub-unidad sale de
        // una división y comparado crudo "cambiaba" en cada guardado.
        $costChanged = round((float) $ingredient->cost_per_unit, 4) !== round((float) $data['cost_per_unit'], 4);

        if ($costChanged) {
            $ingredient->priceLogs()->create([
                'cost_per_unit' => $data['cost_per_unit'],
                'recorded_at' => now(),
            ]);
        }

        $ingredient->update($data);

        if ($costChanged) {
            $this->propagator->propagateFromIngredient($ingredient->id);
        }

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'ingredient',
            targetId: $ingredient->id,
            action: 'ingredient.updated',
            payload: ['name' => $ingredient->name],
            tenantId: $ingredient->tenant_id,
        );

        return back(fallback: route('ingredients.index'))->with('status', 'Ingrediente actualizado.');
    }

    /**
     * La unidad de un insumo en uso no se puede cambiar: las líneas de receta,
     * el stock y el costo quedarían expresados en la unidad anterior.
     */
    private function unitIsLocked(Ingredient $ingredient): bool
    {
        return DB::table('recipe_ingredient_lines')->where('ingredient_id', $ingredient->id)->exists()
            || $ingredient->stockMovements()->exists()
            || DB::table('purchase_lines')
                ->where('purchaseable_type', CatalogItemType::Ingredient->value)
                ->where('purchaseable_id', $ingredient->id)
                ->exists();
    }

    public function toggleActive(Ingredient $ingredient): RedirectResponse
    {
        $this->authorize('update', $ingredient);

        // Sin este guard, un ingrediente descontinuado podía desactivarse sin
        // aviso y las recetas activas seguían costeando con él en silencio.
        // Mismo patrón que RecipeController::toggleActive() para sub-recetas.
        if ($ingredient->active) {
            $blockedBy = DB::table('recipe_ingredient_lines')
                ->join('recipes', 'recipes.id', '=', 'recipe_ingredient_lines.recipe_id')
                ->where('recipe_ingredient_lines.ingredient_id', $ingredient->id)
                ->where('recipes.active', true)
                ->distinct()
                ->pluck('recipes.name')
                ->toArray();

            if (! empty($blockedBy)) {
                return back()->withErrors([
                    'toggle' => 'No podés desactivar este ingrediente porque lo usan: '
                        .implode(', ', $blockedBy).'. Reemplazalo en esas recetas primero.',
                ]);
            }
        }

        $ingredient->update(['active' => ! $ingredient->active]);
        $action = $ingredient->active ? 'ingredient.activated' : 'ingredient.deactivated';

        $this->recorder->record(
            actor: request()->user(),
            targetType: 'ingredient',
            targetId: $ingredient->id,
            action: $action,
            payload: ['name' => $ingredient->name],
            tenantId: $ingredient->tenant_id,
        );

        $label = $ingredient->active ? 'activado' : 'desactivado';

        return back()->with('status', "Ingrediente {$label}.");
    }

    public function convertToProduct(Ingredient $ingredient, Request $request): RedirectResponse
    {
        $this->authorize('update', $ingredient);

        $request->validate([
            'product_category_id' => [
                'nullable', 'integer',
                Rule::exists('product_categories', 'id')->where('tenant_id', $ingredient->tenant_id),
            ],
        ]);

        $categoryId = $request->filled('product_category_id') ? (int) $request->input('product_category_id') : null;

        $product = $this->converter->convert($ingredient, $categoryId);

        $this->recorder->record(
            actor: $request->user(),
            targetType: 'ingredient',
            targetId: $ingredient->id,
            action: 'ingredient.converted_to_product',
            payload: ['ingredient_name' => $ingredient->name, 'product_id' => $product->id],
            tenantId: $ingredient->tenant_id,
        );

        return redirect()->route('products.index')->with('status', "Insumo convertido: {$product->name}.");
    }
}

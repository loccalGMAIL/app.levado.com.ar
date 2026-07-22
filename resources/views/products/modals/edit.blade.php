<x-crud-modal name="product-edit" title="Editar artículo" :show="$errorsInEdit">
    <form method="POST"
        :action="`/products/${editing.id}`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="product_id" x-bind:value="editing.id">

        <div>
            <x-input-label for="edit_product_name" value="Nombre" />
            <x-text-input id="edit_product_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label value="Tipo de artículo" />
            <div class="mt-1 grid grid-cols-2 gap-2">
                <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer text-sm"
                    :class="editing.type === 'manufactured' ? 'border-corteza bg-miga' : 'border-gray-300'">
                    <input type="radio" name="type" value="manufactured" x-model="editing.type" class="text-corteza focus:ring-horno" required>
                    <span>Elaborado <span class="text-xs text-masa-madre">(desde receta)</span></span>
                </label>
                <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer text-sm"
                    :class="editing.type === 'resale' ? 'border-corteza bg-miga' : 'border-gray-300'">
                    <input type="radio" name="type" value="resale" x-model="editing.type" class="text-corteza focus:ring-horno">
                    <span>Reventa <span class="text-xs text-masa-madre">(se compra)</span></span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        {{-- Lista todas las recetas, no sólo las activas: si el producto apunta a una
             dada de baja, su opción tiene que existir o el select caería en vacío. --}}
        <div x-show="editing.type === 'manufactured'" x-cloak>
            <x-input-label for="edit_product_recipe" value="Receta" />
            <select id="edit_product_recipe" name="recipe_id"
                x-model="editing.recipe_id" x-bind:required="editing.type === 'manufactured'"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Seleccioná una receta —</option>
                @foreach($recipes as $recipe)
                    <option value="{{ $recipe->id }}">
                        {{ $recipe->name }}{{ $recipe->active ? '' : ' (inactiva)' }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-masa-madre">El costo del elaborado se toma de la receta.</p>
            <x-input-error :messages="$errors->get('recipe_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="edit_product_category" value="Categoría (opcional)" />
            <select id="edit_product_category" name="product_category_id"
                x-model="editing.product_category_id"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Sin categoría —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-masa-madre">Gestioná las categorías y su flag «se produce» con el botón Categorías.</p>
            <x-input-error :messages="$errors->get('product_category_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_product_unit" value="Unidad de venta" />
                <select id="edit_product_unit" name="unit" required
                    x-model="editing.unit"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}">{{ $unit->short() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>
            <div x-show="editing.type === 'resale'" x-cloak>
                <x-input-label for="edit_product_cost" value="Costo por unidad" />
                <x-text-input id="edit_product_cost" name="cost_per_unit" type="number"
                    step="0.01" min="0"
                    class="mt-1 block w-full"
                    x-model="editing.cost_per_unit"
                    x-bind:required="editing.type === 'resale'" />
                <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_product_sku" value="SKU (opcional)" />
                <x-text-input id="edit_product_sku" name="sku" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.sku" />
                <x-input-error :messages="$errors->get('sku')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_product_barcode" value="Código de barras (opcional)" />
                <x-text-input id="edit_product_barcode" name="barcode" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.barcode" />
                <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'product-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

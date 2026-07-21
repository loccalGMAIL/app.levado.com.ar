<x-crud-modal name="product-create" title="Nuevo artículo" :show="$errorsInCreate">
    <form method="POST" action="{{ route('products.store') }}" class="space-y-4"
          x-data="{ type: '{{ old('type') }}' }">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_product_name" value="Nombre" />
            <x-text-input id="create_product_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label value="Tipo de artículo" />
            <div class="mt-1 grid grid-cols-2 gap-2">
                <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer text-sm"
                    :class="type === 'manufactured' ? 'border-corteza bg-miga' : 'border-gray-300'">
                    <input type="radio" name="type" value="manufactured" x-model="type" class="text-corteza focus:ring-horno" required>
                    <span>Elaborado <span class="text-xs text-masa-madre">(desde receta)</span></span>
                </label>
                <label class="flex items-center gap-2 border rounded-md px-3 py-2 cursor-pointer text-sm"
                    :class="type === 'resale' ? 'border-corteza bg-miga' : 'border-gray-300'">
                    <input type="radio" name="type" value="resale" x-model="type" class="text-corteza focus:ring-horno">
                    <span>Reventa <span class="text-xs text-masa-madre">(se compra)</span></span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        {{-- Elaborado: se elige la receta que lo produce. --}}
        <div x-show="type === 'manufactured'" x-cloak>
            <x-input-label for="create_product_recipe" value="Receta" />
            <select id="create_product_recipe" name="recipe_id" x-bind:required="type === 'manufactured'"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Seleccioná una receta —</option>
                @foreach($recipes->where('active', true) as $recipe)
                    <option value="{{ $recipe->id }}" @selected(old('recipe_id') == $recipe->id)>
                        {{ $recipe->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-masa-madre">El costo del elaborado se toma de la receta.</p>
            <x-input-error :messages="$errors->get('recipe_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_product_unit" value="Unidad de venta" />
                <select id="create_product_unit" name="unit" required
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Seleccioná —</option>
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}" @selected(old('unit') === $unit->value)>
                            {{ $unit->short() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>
            {{-- Reventa: el costo lo carga el usuario (luego lo mantendrá Compras). --}}
            <div x-show="type === 'resale'" x-cloak>
                <x-input-label for="create_product_cost" value="Costo por unidad" />
                <x-text-input id="create_product_cost" name="cost_per_unit" type="number"
                    step="0.01" min="0"
                    class="mt-1 block w-full"
                    :value="old('cost_per_unit')"
                    x-bind:required="type === 'resale'" />
                <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_product_sku" value="SKU (opcional)" />
                <x-text-input id="create_product_sku" name="sku" type="text"
                    class="mt-1 block w-full"
                    :value="old('sku')" />
                <x-input-error :messages="$errors->get('sku')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_product_barcode" value="Código de barras (opcional)" />
                <x-text-input id="create_product_barcode" name="barcode" type="text"
                    class="mt-1 block w-full"
                    :value="old('barcode')" />
                <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear artículo</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'product-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

<x-crud-modal name="recipe-edit-info" title="Editar receta" :show="$errorsInEdit">
    <form method="POST" action="{{ route('recipes.update', $recipe) }}" class="space-y-4">
        @csrf
        @method('PUT')
        <input type="hidden" name="_form" value="edit">

        <div>
            <x-input-label for="edit_recipe_name" value="Nombre" />
            <x-text-input id="edit_recipe_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name', $recipe->name)"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="edit_recipe_desc" value="Descripción (opcional)" />
            <textarea id="edit_recipe_desc" name="description"
                class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                rows="2">{{ old('description', $recipe->description) }}</textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <x-input-label for="edit_recipe_yield" value="Rendimiento" />
                <x-text-input id="edit_recipe_yield" name="yield_quantity" type="number"
                    step="0.001" min="0.001"
                    class="mt-1 block w-full"
                    :value="old('yield_quantity', $recipe->yield_quantity)"
                    required />
                <x-input-error :messages="$errors->get('yield_quantity')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_recipe_unit" value="Unidad" />
                <select id="edit_recipe_unit" name="yield_unit"
                    class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                    required>
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}" {{ old('yield_unit', $recipe->yield_unit->value) === $unit->value ? 'selected' : '' }}>
                            {{ $unit->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('yield_unit')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="edit_recipe_price" :value="'Precio de venta por unidad — lista '.$defaultPriceList->name.' (opcional)'" />
            <x-text-input id="edit_recipe_price" name="selling_price" type="number"
                step="0.01" min="0"
                class="mt-1 block w-full"
                :value="old('selling_price', $defaultPrice)"
                placeholder="Ej: 350.00" />
            <x-input-error :messages="$errors->get('selling_price')" class="mt-2" />
        </div>

        <div class="flex items-start gap-3 pt-1">
            <input type="hidden" name="is_semi_elaborate" value="0">
            <input id="edit_recipe_semi" name="is_semi_elaborate" type="checkbox" value="1"
                class="mt-0.5 rounded border-gray-300 text-corteza focus:ring-corteza"
                {{ old('is_semi_elaborate', $recipe->is_semi_elaborate) ? 'checked' : '' }}>
            <div>
                <x-input-label for="edit_recipe_semi" value="Es una semi-elaboración" />
                <p class="text-xs text-masa-madre mt-0.5">
                    Marcá esto para poder usar esta receta como ingrediente dentro de otras recetas.
                </p>
            </div>
        </div>
        <x-input-error :messages="$errors->get('is_semi_elaborate')" class="mt-2" />

        <div class="flex gap-3 pt-2">
            <x-primary-button>Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'recipe-edit-info')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

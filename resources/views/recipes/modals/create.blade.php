<x-crud-modal name="recipe-create" title="Nueva receta" :show="$errorsInCreate">
    <form method="POST" action="{{ route('recipes.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_recipe_name" value="Nombre" />
            <x-text-input id="create_recipe_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                placeholder="Ej: Medialunas de manteca, Pan de campo"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="create_recipe_desc" value="Descripción (opcional)" />
            <textarea id="create_recipe_desc" name="description"
                class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                rows="2"
                placeholder="Notas sobre la receta, versión, etc.">{{ old('description') }}</textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <x-input-label for="create_recipe_yield" value="Rendimiento" />
                <x-text-input id="create_recipe_yield" name="yield_quantity" type="number"
                    step="0.001" min="0.001"
                    class="mt-1 block w-full"
                    :value="old('yield_quantity', 1)"
                    required />
                <x-input-error :messages="$errors->get('yield_quantity')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_recipe_unit" value="Unidad" />
                <select id="create_recipe_unit" name="yield_unit"
                    class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                    required>
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}" {{ old('yield_unit', \App\Enums\Unit::Unidad->value) === $unit->value ? 'selected' : '' }}>
                            {{ $unit->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('yield_unit')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="create_recipe_price" value="Precio de venta por unidad (opcional)" />
            <x-text-input id="create_recipe_price" name="selling_price" type="number"
                step="0.01" min="0"
                class="mt-1 block w-full"
                :value="old('selling_price')"
                placeholder="Ej: 350.00" />
            <x-input-error :messages="$errors->get('selling_price')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Crear receta</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'recipe-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

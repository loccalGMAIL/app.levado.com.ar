<x-crud-modal name="recipe-add-ingredient" title="Agregar ingrediente" :show="$errorsInAddIngredient">
    <form method="POST" action="{{ route('recipes.ingredient-lines.store', $recipe) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="add-ingredient">

        <div>
            <x-input-label for="add_ing_id" value="Ingrediente" />
            <select id="add_ing_id" name="ingredient_id"
                class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                required>
                <option value="">— seleccionar —</option>
                @foreach($ingredients as $ingredient)
                    <option value="{{ $ingredient->id }}"
                        data-unit="{{ $ingredient->unit->value }}"
                        {{ old('ingredient_id') == $ingredient->id ? 'selected' : '' }}>
                        {{ $ingredient->name }} ({{ $ingredient->unit->short() }})
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('ingredient_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <x-input-label for="add_ing_qty" value="Cantidad" />
                <x-text-input id="add_ing_qty" name="quantity" type="number"
                    step="0.001" min="0.001"
                    class="mt-1 block w-full"
                    :value="old('quantity')"
                    required />
                <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="add_ing_unit" value="Unidad" />
                <select id="add_ing_unit" name="unit"
                    class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                    required>
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}" {{ old('unit') === $unit->value ? 'selected' : '' }}>
                            {{ $unit->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>
        </div>
        <p class="text-xs text-masa-madre">La unidad debe ser compatible con la del ingrediente (peso, volumen o unidad).</p>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Agregar</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'recipe-add-ingredient')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

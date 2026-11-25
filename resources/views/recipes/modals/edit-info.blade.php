<x-crud-modal name="recipe-edit-info" title="Editar receta" max-width="2xl" :show="$errorsInEdit">
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

        {{-- Rendimiento y, opcional, la unidad de producción: si lo que rinde se
             pide en bandejas o latas en las planillas de pedidos. --}}
        <div class="grid grid-cols-2 sm:grid-cols-[1fr_0.8fr_1.25fr_1fr] gap-3"
            x-data="{ container: @js(old('container_type', $recipe->container_type?->value ?? '')) }">
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
                            {{ $unit->short() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('yield_unit')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_recipe_container_type" value="Unidad prod." class="whitespace-nowrap" />
                <select id="edit_recipe_container_type" name="container_type" x-model="container"
                    class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm">
                    <option value="">Unidades</option>
                    @foreach(\App\Enums\ContainerType::cases() as $containerType)
                        <option value="{{ $containerType->value }}">{{ ucfirst($containerType->plural()) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('container_type')" class="mt-2" />
            </div>
            <div x-show="container" x-cloak>
                <x-input-label for="edit_recipe_units_per_container" value="Cuánto entra en una" class="whitespace-nowrap" />
                <x-text-input id="edit_recipe_units_per_container" name="units_per_container" type="number"
                    step="0.001" min="0.001"
                    class="mt-1 block w-full"
                    :value="old('units_per_container', $recipe->units_per_container)"
                    x-bind:required="container !== ''" />
                <x-input-error :messages="$errors->get('units_per_container')" class="mt-2" />
            </div>
        </div>

        {{-- Producción: lo que pasa por cámara se amasa el día anterior y
             espera en cámara; lo directo se amasa y hornea el mismo día. --}}
        <div class="flex flex-wrap items-start gap-x-6 gap-y-3"
            x-data="{ proofing: @js((bool) old('uses_proofing', $recipe->uses_proofing)) }">
            <div class="flex items-start gap-3 pt-1">
                <input type="hidden" name="uses_proofing" value="0">
                <input id="edit_recipe_proofing" name="uses_proofing" type="checkbox" value="1"
                    class="mt-0.5 rounded border-gray-300 text-corteza focus:ring-corteza"
                    x-model="proofing">
                <x-input-label for="edit_recipe_proofing" value="Pasa por cámara de leudado" />
            </div>
            <div x-show="proofing" x-cloak class="flex items-center gap-2">
                <x-input-label for="edit_recipe_max_proofing_hours" value="Horas máx." class="!mb-0 whitespace-nowrap" />
                <x-text-input id="edit_recipe_max_proofing_hours" name="max_proofing_hours" type="number"
                    step="1" min="1" max="999"
                    class="block w-20"
                    :value="old('max_proofing_hours', $recipe->max_proofing_hours)" />
            </div>
            <x-input-error :messages="$errors->get('uses_proofing')" class="w-full" />
            <x-input-error :messages="$errors->get('max_proofing_hours')" class="w-full" />
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
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'recipe-edit-info')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

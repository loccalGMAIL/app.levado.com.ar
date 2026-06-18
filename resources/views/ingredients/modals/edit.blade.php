<x-crud-modal name="ingredient-edit" title="Editar ingrediente" :show="$errorsInEdit">
    <form method="POST"
        :action="`/ingredients/${editing.id}`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="ingredient_id" x-bind:value="editing.id">

        <div>
            <x-input-label for="edit_name" value="Nombre" />
            <x-text-input id="edit_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_brand" value="Marca" />
                <x-text-input id="edit_brand" name="brand" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.brand" />
                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <x-input-label for="edit_supplier" value="Proveedor" />
                    <button type="button"
                        @click="$dispatch('close-modal', 'ingredient-edit'); $nextTick(() => $dispatch('open-modal', 'supplier-quick-create'))"
                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                        + Nuevo
                    </button>
                </div>
                <select id="edit_supplier" name="supplier_id"
                    x-model="editing.supplier_id"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Ninguno —</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_unit" value="Unidad de medida" />
                <select id="edit_unit" name="unit" required
                    x-model="editing.unit"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}">{{ $unit->label() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_cost" value="Costo por unidad" />
                <x-text-input id="edit_cost" name="cost_per_unit" type="number"
                    step="0.0001" min="0"
                    class="mt-1 block w-full"
                    x-model="editing.cost_per_unit"
                    required />
                <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'ingredient-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

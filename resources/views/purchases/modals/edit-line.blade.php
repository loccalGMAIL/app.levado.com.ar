<x-crud-modal name="line-edit" title="Editar ítem" :show="$errorsInEditLine">
    <form method="POST" :action="'/purchases/{{ $purchase->id }}/lines/' + editingLine?.id" class="space-y-4">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_form" value="edit-line">
        <input type="hidden" name="line_id" :value="editingLine?.id">

        <p class="text-sm font-medium text-corteza" x-text="editingLine?.item_name"></p>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label value="Cantidad comprada" />
                <x-text-input name="quantity_purchased" type="number"
                    step="0.0001" min="0.0001"
                    class="mt-1 block w-full"
                    x-bind:value="editingLine?.quantity_purchased"
                    required />
                <x-input-error :messages="$errors->get('quantity_purchased')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Unidad" />
                <select name="purchase_unit" required
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    @foreach($units as $unit)
                        <option value="{{ $unit->value }}"
                            x-bind:selected="editingLine?.purchase_unit === '{{ $unit->value }}'">
                            {{ $unit->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('purchase_unit')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label value="Precio por unidad de compra" />
            <div class="relative mt-1">
                <span class="absolute inset-y-0 left-3 flex items-center text-masa-madre text-sm">$</span>
                <x-text-input name="unit_price" type="number"
                    step="0.01" min="0"
                    class="block w-full pl-7"
                    x-bind:value="editingLine?.unit_price"
                    required />
            </div>
            <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'line-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

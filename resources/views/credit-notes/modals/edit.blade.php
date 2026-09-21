<x-crud-modal name="credit-note-edit" title="Editar nota de crédito" :show="$errorsInEditCreditNote">
    <form method="POST" action="{{ route('credit-notes.update', $creditNote) }}" class="space-y-4">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_form" value="credit-note-edit">
        {{-- No hay UI para reasignar la compra de origen desde acá: se preserva
             tal cual, si no el nullable de purchase_id la desvincularía sola. --}}
        <input type="hidden" name="purchase_id" value="{{ old('purchase_id', $creditNote->purchase_id) }}">

        <div>
            <x-input-label for="edit_cn_supplier" value="Proveedor" />
            <select id="edit_cn_supplier" name="supplier_id" required
                data-searchable
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Seleccioná un proveedor —</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}"
                        @selected(old('supplier_id', $creditNote->supplier_id) == $supplier->id)>
                        {{ $supplier->name }}{{ $supplier->active ? '' : ' (inactivo)' }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_cn_date" value="Fecha de la nota" />
                <x-text-input id="edit_cn_date" name="note_date" type="date"
                    class="mt-1 block w-full"
                    :value="old('note_date', $creditNote->note_date->format('Y-m-d'))"
                    required />
                <x-input-error :messages="$errors->get('note_date')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_cn_number" value="N° de nota" />
                <x-text-input id="edit_cn_number" name="note_number" type="text"
                    class="mt-1 block w-full"
                    :value="old('note_number', $creditNote->note_number)"
                    placeholder="Opcional" />
                <x-input-error :messages="$errors->get('note_number')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="edit_cn_notes" value="Notas" />
            <textarea id="edit_cn_notes" name="notes"
                rows="2"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm"
                placeholder="Opcional">{{ old('notes', $creditNote->notes) }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'credit-note-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

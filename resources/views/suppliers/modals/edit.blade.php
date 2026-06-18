<x-crud-modal name="supplier-edit" title="Editar proveedor" :show="$errorsInEdit">
    <form method="POST"
        :action="`/suppliers/${editing.id}`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="supplier_id" x-bind:value="editing.id">

        <div>
            <x-input-label for="edit_supplier_name" value="Nombre" />
            <x-text-input id="edit_supplier_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_supplier_phone" value="Teléfono" />
                <x-text-input id="edit_supplier_phone" name="phone" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.phone" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_supplier_email" value="Email" />
                <x-text-input id="edit_supplier_email" name="email" type="email"
                    class="mt-1 block w-full"
                    x-model="editing.email" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="edit_supplier_notes" value="Notas" />
            <textarea id="edit_supplier_notes" name="notes" rows="2"
                x-model="editing.notes"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm"></textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'supplier-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

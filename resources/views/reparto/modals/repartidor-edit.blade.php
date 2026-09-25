<x-crud-modal name="delivery-person-edit" title="Editar repartidor" :show="$errorsInEdit">
    <form method="POST"
        :action="`/reparto/repartidores/${editing.id}`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="delivery_person_id" x-bind:value="editing.id">

        <div>
            <x-input-label for="edit_name" value="Nombre" />
            <x-text-input id="edit_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="edit_phone" value="Teléfono" />
            <x-text-input id="edit_phone" name="phone" type="text"
                class="mt-1 block w-full"
                x-model="editing.phone" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="edit_notes" value="Notas" />
            <textarea id="edit_notes" name="notes" rows="2"
                x-model="editing.notes"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-horno focus:ring-horno text-sm"></textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'delivery-person-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

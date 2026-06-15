<x-crud-modal name="location-edit" title="Editar sucursal" :show="$errorsInEdit">
    <form method="POST"
        :action="`/locations/${editing.id}`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="location_id" x-bind:value="editing.id">

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
                <x-input-label for="edit_address" value="Dirección" />
                <x-text-input id="edit_address" name="address" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.address" />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_city" value="Ciudad" />
                <x-text-input id="edit_city" name="city" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.city" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <input id="edit_is_default" name="is_default" type="checkbox" value="1"
                class="rounded border-gray-300 text-horno focus:ring-horno"
                x-model="editing.is_default" />
            <x-input-label for="edit_is_default" value="Sucursal principal" class="!mb-0" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'location-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

<x-crud-modal name="labor-type-edit" title="Editar tipo de mano de obra" :show="$errorsInEdit">
    <form method="POST"
        :action="`/labor-types/${editing.id}`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="labor_type_id" x-bind:value="editing.id">

        <div>
            <x-input-label for="edit_lt_name" value="Nombre del rol" />
            <x-text-input id="edit_lt_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="edit_lt_rate" value="Costo por hora" />
            <x-text-input id="edit_lt_rate" name="hourly_rate" type="number"
                step="0.01" min="0"
                class="mt-1 block w-full"
                x-model="editing.hourly_rate"
                required />
            <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'labor-type-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

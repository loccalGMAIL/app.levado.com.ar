<x-crud-modal name="price-list-edit" title="Editar lista de precios" :show="$errorsInEdit">
    <form method="POST"
        :action="`/price-lists/${editing.id}`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="price_list_id" x-bind:value="editing.id">
        <input type="hidden" name="is_default_flag" x-bind:value="editing.is_default ? 1 : ''">

        <div>
            <x-input-label for="edit_pl_name" value="Nombre de la lista" />
            <x-text-input id="edit_pl_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div x-show="!editing.is_default">
            <x-input-label for="edit_pl_pct" value="% de ajuste sobre la lista base (opcional)" />
            <x-text-input id="edit_pl_pct" name="adjustment_pct" type="number"
                step="0.01" min="-99.99" max="999.99"
                class="mt-1 block w-full"
                x-model="editing.adjustment_pct" />
            <p class="mt-1 text-xs text-masa-madre">Solo sugiere precios: en cada receta podés cargar el monto que quieras.</p>
            <x-input-error :messages="$errors->get('adjustment_pct')" class="mt-2" />
        </div>

        <p x-show="editing.is_default" class="text-xs text-masa-madre">
            Esta es la lista base: los % de ajuste de las demás listas se calculan sobre sus precios.
        </p>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'price-list-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

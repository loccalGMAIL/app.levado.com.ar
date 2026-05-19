<x-crud-modal name="fixed-cost-edit" title="Editar gasto fijo" :show="$errorsInEdit">
    <form method="POST"
        :action="`/fixed-costs/${editing.id}`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="fixed_cost_id" x-bind:value="editing.id">

        <div>
            <x-input-label for="edit_fc_name" value="Nombre" />
            <x-text-input id="edit_fc_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_fc_category" value="Categoría" />
                <select id="edit_fc_category" name="fixed_cost_category_id"
                    x-model="editing.fixed_cost_category_id"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm"
                    required>
                    <option value="">— Seleccioná —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('fixed_cost_category_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_fc_valid_from" value="Vigente desde" />
                <x-text-input id="edit_fc_valid_from" name="valid_from" type="date"
                    class="mt-1 block w-full"
                    x-model="editing.valid_from"
                    required />
                <p class="mt-1 text-xs text-masa-madre">Fecha de vigencia del nuevo monto.</p>
                <x-input-error :messages="$errors->get('valid_from')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="edit_fc_amount" value="Monto mensual" />
            <x-text-input id="edit_fc_amount" name="monthly_amount" type="number"
                step="0.01" min="0"
                class="mt-1 block w-full"
                x-model="editing.monthly_amount"
                required />
            <x-input-error :messages="$errors->get('monthly_amount')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'fixed-cost-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

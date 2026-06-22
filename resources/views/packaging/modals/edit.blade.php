<x-crud-modal name="packaging-edit" title="Editar envase" :show="$errorsInEdit">
    <form method="POST"
        :action="`/packaging/${editing.id}`"
        class="space-y-4"
        x-on:supplier-created.window="
            const sel = document.getElementById('edit_pkg_supplier');
            sel.add(new Option($event.detail.name, $event.detail.id, true, true));
            editing.supplier_id = $event.detail.id;
        ">
        @csrf
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="_form" value="edit">
        <input type="hidden" name="packaging_id" x-bind:value="editing.id">

        <div>
            <x-input-label for="edit_pkg_name" value="Nombre" />
            <x-text-input id="edit_pkg_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="editing.name"
                required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="edit_pkg_brand" value="Marca" />
                <x-text-input id="edit_pkg_brand" name="brand" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.brand" />
                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <x-input-label for="edit_pkg_supplier" value="Proveedor" />
                    <button type="button"
                        @click="$dispatch('open-modal', 'supplier-quick-create')"
                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                        + Nuevo
                    </button>
                </div>
                <select id="edit_pkg_supplier" name="supplier_id"
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

        <div>
            <x-input-label for="edit_pkg_cost"
                x-text="editing.subdivisions ? 'Costo por presentación' : 'Costo por unidad'" />
            <x-text-input id="edit_pkg_cost" name="cost_per_unit" type="number"
                step="0.0001" min="0"
                class="mt-1 block w-full"
                x-model="editing.cost_per_unit"
                required />
            <p class="mt-1 text-xs text-masa-madre"
                x-show="editing.subdivisions && editing.subdivisions > 1 && editing.cost_per_unit"
                x-text="'≈ $' + (parseFloat(editing.cost_per_unit) / (parseInt(editing.subdivisions) || 1)).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 4}) + ' / ' + (editing.subdivision_label || 'sub-unidad')">
            </p>
            <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4 border-t border-miga pt-4">
            <div>
                <x-input-label for="edit_pkg_subdivisions" value="Unidades por presentación" />
                <x-text-input id="edit_pkg_subdivisions" name="subdivisions" type="number"
                    step="1" min="2"
                    class="mt-1 block w-full"
                    x-model="editing.subdivisions" />
                <p class="mt-1 text-xs text-masa-madre">Ej.: 100 bolsas por caja.</p>
                <x-input-error :messages="$errors->get('subdivisions')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_pkg_subdivision_label" value="Nombre de la unidad" />
                <x-text-input id="edit_pkg_subdivision_label" name="subdivision_label" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.subdivision_label"
                    placeholder="bolsa, etiqueta…" />
                <x-input-error :messages="$errors->get('subdivision_label')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'packaging-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

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
                {{-- Lista todos los proveedores, no sólo los activos: si el ingrediente apunta
                     a uno dado de baja, su opción tiene que existir o el select caería en
                     «Ninguno» y guardar borraría el proveedor en silencio. --}}
                <select id="edit_supplier" name="supplier_id"
                    x-model="editing.supplier_id"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Ninguno —</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">
                            {{ $supplier->name }}{{ $supplier->active ? '' : ' (inactivo)' }}
                        </option>
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
                        <option value="{{ $unit->value }}">{{ $unit->short() }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_cost"
                    x-text="editing.subdivisions && editing.unit === 'u' ? 'Costo por envase' : 'Costo por unidad'" />
                <x-text-input id="edit_cost" name="cost_per_unit" type="number"
                    step="0.01" min="0"
                    class="mt-1 block w-full"
                    x-model="editing.cost_per_unit"
                    required />
                <p class="mt-1 text-xs text-masa-madre"
                    x-show="editing.subdivisions && editing.unit === 'u' && editing.cost_per_unit && editing.subdivisions > 1"
                    x-text="'≈ $' + (parseFloat(editing.cost_per_unit) / (parseInt(editing.subdivisions) || 1)).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' / ' + (editing.subdivision_label || 'sub-unidad')">
                </p>
                <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
            </div>
        </div>

        <div x-show="editing.unit === 'u'" class="grid grid-cols-2 gap-4 border-t border-miga pt-4">
            <div>
                <x-input-label for="edit_subdivisions" value="Unidades por envase" />
                <x-text-input id="edit_subdivisions" name="subdivisions" type="number"
                    step="1" min="2"
                    class="mt-1 block w-full"
                    x-model="editing.subdivisions" />
                <p class="mt-1 text-xs text-masa-madre">Ej.: 24 galletas por paquete.</p>
                <x-input-error :messages="$errors->get('subdivisions')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="edit_subdivision_label" value="Nombre de la unidad" />
                <x-text-input id="edit_subdivision_label" name="subdivision_label" type="text"
                    class="mt-1 block w-full"
                    x-model="editing.subdivision_label"
                    placeholder="galleta, rebanada…" />
                <x-input-error :messages="$errors->get('subdivision_label')" class="mt-2" />
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

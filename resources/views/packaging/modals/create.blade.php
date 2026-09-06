<x-crud-modal name="packaging-create" title="Nuevo envase" :show="$errorsInCreate">
    <form method="POST" action="{{ route('packaging.store') }}" class="space-y-4"
          x-data="{ pkgCreateCost: '{{ old('cost_per_unit') }}', pkgCreateSubdivisions: '{{ old('subdivisions') }}', pkgCreateSubdivisionLabel: '{{ old('subdivision_label') }}' }"
          x-on:supplier-created.window="
              const sel = document.getElementById('create_pkg_supplier');
              sel.add(new Option($event.detail.name, $event.detail.id, true, true));
          ">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_pkg_name" value="Nombre" />
            <x-text-input id="create_pkg_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_pkg_brand" value="Marca" />
                <x-text-input id="create_pkg_brand" name="brand" type="text"
                    class="mt-1 block w-full"
                    :value="old('brand')" />
                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <x-input-label for="create_pkg_supplier" value="Proveedor" />
                    <button type="button"
                        @click="$dispatch('open-modal', 'supplier-quick-create')"
                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                        + Nuevo
                    </button>
                </div>
                <select id="create_pkg_supplier" name="supplier_id"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Ninguno —</option>
                    {{-- Sólo activos: $suppliers trae todos porque el modal de edición los necesita. --}}
                    @foreach($suppliers->where('active', true) as $supplier)
                        <option value="{{ $supplier->id }}"
                            @selected(old('supplier_id') == $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="create_pkg_cost"
                x-text="parseInt(pkgCreateSubdivisions) > 1 ? 'Costo por presentación' : 'Costo por unidad'" />
            <x-text-input id="create_pkg_cost" name="cost_per_unit" type="number"
                step="0.01" min="0"
                class="mt-1 block w-full"
                :value="old('cost_per_unit')"
                x-model="pkgCreateCost"
                required />
            <p class="mt-1 text-xs text-masa-madre"
                x-show="parseInt(pkgCreateSubdivisions) > 1 && pkgCreateCost"
                x-text="'≈ $' + (parseFloat(pkgCreateCost) / (parseInt(pkgCreateSubdivisions) || 1)).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' / ' + (pkgCreateSubdivisionLabel || 'sub-unidad')">
            </p>
            <p class="mt-1 text-xs text-masa-madre"
                x-show="parseInt(pkgCreateSubdivisions) <= 1">En la moneda del negocio.</p>
            <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4 border-t border-miga pt-4">
            <div>
                <x-input-label for="create_pkg_subdivisions" value="Unidades por presentación (opcional)" />
                <x-text-input id="create_pkg_subdivisions" name="subdivisions" type="number"
                    step="1"
                    class="mt-1 block w-full"
                    :value="old('subdivisions')"
                    x-model="pkgCreateSubdivisions" />
                <p class="mt-1 text-xs text-masa-madre">
                    Completar sólo si se subdivide (ej.: 100 bolsas por caja).
                </p>
                <p class="mt-1 text-xs text-horno"
                    x-show="pkgCreateSubdivisions !== '' && parseInt(pkgCreateSubdivisions) < 2">
                    Si no se subdivide, dejá este campo vacío.
                    <button type="button" class="underline hover:no-underline" @click="pkgCreateSubdivisions = ''">Vaciar</button>
                </p>
                <x-input-error :messages="$errors->get('subdivisions')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_pkg_subdivision_label" value="Nombre de la unidad" />
                <x-text-input id="create_pkg_subdivision_label" name="subdivision_label" type="text"
                    class="mt-1 block w-full"
                    :value="old('subdivision_label')"
                    x-model="pkgCreateSubdivisionLabel"
                    placeholder="bolsa, etiqueta…" />
                <x-input-error :messages="$errors->get('subdivision_label')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear envase</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'packaging-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

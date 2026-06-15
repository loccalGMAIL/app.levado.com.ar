<x-crud-modal name="ingredient-create" title="Nuevo ingrediente" :show="$errorsInCreate">
    <form method="POST" action="{{ route('ingredients.store') }}" class="space-y-4"
          x-on:supplier-created.window="
              const sel = document.getElementById('create_supplier');
              sel.add(new Option($event.detail.name, $event.detail.id, true, true));
          ">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_name" value="Nombre" />
            <x-text-input id="create_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_brand" value="Marca" />
                <x-text-input id="create_brand" name="brand" type="text"
                    class="mt-1 block w-full"
                    :value="old('brand')" />
                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <x-input-label for="create_supplier" value="Proveedor" />
                    <button type="button"
                        @click="$dispatch('open-modal', 'supplier-quick-create')"
                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                        + Nuevo
                    </button>
                </div>
                <select id="create_supplier" name="supplier_id"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Ninguno —</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}"
                            @selected(old('supplier_id') == $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_unit" value="Unidad de medida" />
                <select id="create_unit" name="unit" required
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Seleccioná —</option>
                    @foreach(\App\Enums\Unit::cases() as $unit)
                        <option value="{{ $unit->value }}"
                            @selected(old('unit') === $unit->value)>
                            {{ $unit->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_cost" value="Costo por unidad" />
                <x-text-input id="create_cost" name="cost_per_unit" type="number"
                    step="0.0001" min="0"
                    class="mt-1 block w-full"
                    :value="old('cost_per_unit')"
                    required />
                <p class="mt-1 text-xs text-masa-madre">En la moneda del negocio.</p>
                <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear ingrediente</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'ingredient-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

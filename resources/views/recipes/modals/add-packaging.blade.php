<x-crud-modal name="recipe-add-packaging" title="Agregar envase" :show="$errorsInAddPackaging">
    <form method="POST" action="{{ route('recipes.packaging-lines.store', $recipe) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="add-packaging">

        <div>
            <x-input-label for="add_pkg_id" value="Envase" />
            <select id="add_pkg_id" name="packaging_id"
                class="mt-1 block w-full border-gray-300 focus:border-corteza focus:ring-corteza rounded-md shadow-sm text-sm"
                required>
                <option value="">— seleccionar —</option>
                @foreach($packagings as $packaging)
                    <option value="{{ $packaging->id }}" {{ old('packaging_id') == $packaging->id ? 'selected' : '' }}>
                        {{ $packaging->name }}{{ $packaging->brand ? ' — '.$packaging->brand : '' }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('packaging_id')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="add_pkg_qty" value="Cantidad (unidades)" />
            <x-text-input id="add_pkg_qty" name="quantity" type="number"
                step="0.001" min="0.001"
                class="mt-1 block w-full"
                :value="old('quantity', 1)"
                required />
            <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Agregar</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'recipe-add-packaging')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

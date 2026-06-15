<x-crud-modal name="location-create" title="Nueva sucursal" :show="$errorsInCreate">
    <form method="POST" action="{{ route('locations.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_name" value="Nombre" />
            <x-text-input id="create_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <p class="mt-1 text-xs text-masa-madre">Ej: Casa Central, Sucursal Norte, Depósito.</p>
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_address" value="Dirección" />
                <x-text-input id="create_address" name="address" type="text"
                    class="mt-1 block w-full"
                    :value="old('address')" />
                <x-input-error :messages="$errors->get('address')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_city" value="Ciudad" />
                <x-text-input id="create_city" name="city" type="text"
                    class="mt-1 block w-full"
                    :value="old('city')" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <input id="create_is_default" name="is_default" type="checkbox" value="1"
                class="rounded border-gray-300 text-horno focus:ring-horno"
                @checked(old('is_default')) />
            <x-input-label for="create_is_default" value="Marcar como sucursal principal" class="!mb-0" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear sucursal</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'location-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

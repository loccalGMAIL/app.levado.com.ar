<x-crud-modal name="labor-type-create" title="Nuevo tipo de mano de obra" :show="$errorsInCreate">
    <form method="POST" action="{{ route('labor-types.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_lt_name" value="Nombre del rol" />
            <x-text-input id="create_lt_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                placeholder="Ej: Panadero, Pastelero, Ayudante"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="create_lt_rate" value="Costo por hora" />
            <x-text-input id="create_lt_rate" name="hourly_rate" type="number"
                step="0.01" min="0"
                class="mt-1 block w-full"
                :value="old('hourly_rate')"
                required />
            <p class="mt-1 text-xs text-masa-madre">En la moneda del negocio.</p>
            <x-input-error :messages="$errors->get('hourly_rate')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Crear tipo</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'labor-type-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

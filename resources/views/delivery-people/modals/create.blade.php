<x-crud-modal name="delivery-person-create" title="Nuevo repartidor" :show="$errorsInCreate">
    <form method="POST" action="{{ route('delivery-people.store') }}" class="space-y-4">
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

        <div>
            <x-input-label for="create_phone" value="Teléfono" />
            <x-text-input id="create_phone" name="phone" type="text"
                class="mt-1 block w-full"
                :value="old('phone')" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="create_notes" value="Notas" />
            <textarea id="create_notes" name="notes" rows="2"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-horno focus:ring-horno text-sm">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear repartidor</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'delivery-person-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

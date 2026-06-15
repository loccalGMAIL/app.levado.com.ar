<x-crud-modal name="supplier-create" title="Nuevo proveedor" :show="$errorsInCreate">
    <form method="POST" action="{{ route('suppliers.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <x-input-label for="create_supplier_name" value="Nombre" />
            <x-text-input id="create_supplier_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_supplier_phone" value="Teléfono" />
                <x-text-input id="create_supplier_phone" name="phone" type="text"
                    class="mt-1 block w-full"
                    :value="old('phone')" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_supplier_email" value="Email" />
                <x-text-input id="create_supplier_email" name="email" type="email"
                    class="mt-1 block w-full"
                    :value="old('email')" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="create_supplier_notes" value="Notas" />
            <textarea id="create_supplier_notes" name="notes" rows="2"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Crear proveedor</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'supplier-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

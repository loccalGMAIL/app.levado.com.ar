<x-crud-modal name="supplier-quick-create" title="Nuevo proveedor" :show="$supplierErrorsInCreate">
    <form method="POST" action="{{ route('suppliers.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="supplier-quick-create">

        <div>
            <x-input-label for="qc_supplier_name" value="Nombre" />
            <x-text-input id="qc_supplier_name" name="name" type="text"
                class="mt-1 block w-full"
                :value="old('name')"
                required autofocus />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="qc_supplier_phone" value="Teléfono" />
                <x-text-input id="qc_supplier_phone" name="phone" type="text"
                    class="mt-1 block w-full"
                    :value="old('phone')" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="qc_supplier_email" value="Email" />
                <x-text-input id="qc_supplier_email" name="email" type="email"
                    class="mt-1 block w-full"
                    :value="old('email')" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button>Crear proveedor</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'supplier-quick-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

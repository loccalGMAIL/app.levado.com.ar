<x-crud-modal name="supplier-quick-create" title="Nuevo proveedor" :show="false" :z="60">
    <div x-data="{
            name: '',
            phone: '',
            email: '',
            loading: false,
            errors: {},
            async submit() {
                this.loading = true;
                this.errors = {};
                try {
                    const res = await fetch('{{ route('suppliers.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ name: this.name, phone: this.phone, email: this.email }),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.errors = data?.errors ?? {};
                        return;
                    }
                    $dispatch('supplier-created', { id: data.id, name: data.name });
                    $dispatch('close-modal', 'supplier-quick-create');
                    this.name = '';
                    this.phone = '';
                    this.email = '';
                } catch {
                    this.errors = { name: ['Error al crear el proveedor.'] };
                } finally {
                    this.loading = false;
                }
            }
        }"
        class="space-y-4">

        <div>
            <x-input-label for="qc_supplier_name" value="Nombre" />
            <x-text-input id="qc_supplier_name" name="name" type="text"
                class="mt-1 block w-full"
                x-model="name"
                @keydown.enter.prevent="submit()"
                required autofocus />
            <p x-show="errors.name" x-text="errors.name?.[0]" class="mt-1 text-xs text-red-500"></p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="qc_supplier_phone" value="Teléfono" />
                <x-text-input id="qc_supplier_phone" name="phone" type="text"
                    class="mt-1 block w-full"
                    x-model="phone" />
                <p x-show="errors.phone" x-text="errors.phone?.[0]" class="mt-1 text-xs text-red-500"></p>
            </div>
            <div>
                <x-input-label for="qc_supplier_email" value="Email" />
                <x-text-input id="qc_supplier_email" name="email" type="email"
                    class="mt-1 block w-full"
                    x-model="email" />
                <p x-show="errors.email" x-text="errors.email?.[0]" class="mt-1 text-xs text-red-500"></p>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="button"
                @click="submit()"
                :disabled="loading || !name.trim()"
                class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors disabled:opacity-50">
                <span x-text="loading ? 'Creando…' : 'Crear proveedor'"></span>
            </button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'supplier-quick-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </div>
</x-crud-modal>

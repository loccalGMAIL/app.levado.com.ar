<x-crud-modal name="purchase-create" title="Nueva compra" :show="$errorsInCreate">
    <form method="POST" action="{{ route('purchases.store') }}" class="space-y-4"
          x-on:supplier-created.window="
              const sel = document.getElementById('create_purchase_supplier');
              if (sel && sel._ts) {
                  sel._ts.addOption({ value: String($event.detail.id), text: $event.detail.name });
                  sel._ts.setValue(String($event.detail.id));
              }
          ">
        @csrf
        <input type="hidden" name="_form" value="create">

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="create_purchase_supplier" value="Proveedor" />
                <button type="button"
                    @click="$dispatch('open-modal', 'supplier-quick-create')"
                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                    + Nuevo proveedor
                </button>
            </div>
            <select id="create_purchase_supplier" name="supplier_id" required
                data-searchable
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Seleccioná un proveedor —</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}"
                        @selected(old('supplier_id') == $supplier->id)>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_purchase_date" value="Fecha de compra" />
                <x-text-input id="create_purchase_date" name="invoice_date" type="date"
                    class="mt-1 block w-full"
                    :value="old('invoice_date', date('Y-m-d'))"
                    required />
                <x-input-error :messages="$errors->get('invoice_date')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_purchase_invoice" value="N° de factura" />
                <x-text-input id="create_purchase_invoice" name="invoice_number" type="text"
                    class="mt-1 block w-full"
                    :value="old('invoice_number')"
                    placeholder="Opcional" />
                <x-input-error :messages="$errors->get('invoice_number')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="create_purchase_notes" value="Notas" />
            <textarea id="create_purchase_notes" name="notes"
                rows="2"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm"
                placeholder="Opcional">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Registrar compra</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'purchase-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

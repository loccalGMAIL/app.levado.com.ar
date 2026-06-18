<x-crud-modal name="line-edit" title="Editar ítem" :show="$errorsInEditLine">
    <form method="POST" :action="'/purchases/{{ $purchase->id }}/lines/' + editingLine?.id" class="space-y-4"
        x-data="{
            qty: 0,
            price: 0,
            ivaRate: parseFloat(editingLine?.iva_rate ?? 0.21),
            percepcionRate: parseFloat(editingLine?.percepcion_rate ?? 0),
            get net()              { return this.qty * this.price; },
            get ivaAmount()        { return this.net * this.ivaRate; },
            get percepcionAmount() { return this.net * (this.percepcionRate / 100); },
            get subtotalTotal()    { return this.net * (1 + this.ivaRate + this.percepcionRate / 100); },
            fmt(n) { return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        }"
        x-effect="qty = parseFloat(editingLine?.quantity_purchased) || 0; price = parseFloat(editingLine?.unit_price) || 0; ivaRate = parseFloat(editingLine?.iva_rate ?? 0.21); percepcionRate = parseFloat(editingLine?.percepcion_rate ?? 0);">
        @csrf
        @method('PATCH')
        <input type="hidden" name="_form" value="edit-line">
        <input type="hidden" name="line_id" :value="editingLine?.id">

        <div>
            <x-input-label value="Descripción" />
            <x-text-input name="raw_name" type="text"
                class="mt-1 block w-full"
                x-bind:value="editingLine?.raw_name" />
            <x-input-error :messages="$errors->get('raw_name')" class="mt-1" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label value="Cantidad comprada" />
                <x-text-input name="quantity_purchased" type="number"
                    step="0.0001" min="0.0001"
                    data-maxdecimals="4"
                    class="mt-1 block w-full"
                    x-bind:value="editingLine?.quantity_purchased"
                    @input="qty = parseFloat($event.target.value) || 0"
                    required />
                <x-input-error :messages="$errors->get('quantity_purchased')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Unidad" />
                <select name="purchase_unit" required
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    @foreach($units as $unit)
                        <option value="{{ $unit->value }}"
                            x-bind:selected="editingLine?.purchase_unit === '{{ $unit->value }}'">
                            {{ $unit->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('purchase_unit')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label value="Precio por unidad de compra" />
            <div class="relative mt-1">
                <span class="absolute inset-y-0 left-3 flex items-center text-masa-madre text-sm">$</span>
                <x-text-input name="unit_price" type="number"
                    step="0.0001" min="0"
                    data-maxdecimals="4"
                    class="block w-full pl-7"
                    x-bind:value="editingLine?.unit_price"
                    @input="price = parseFloat($event.target.value) || 0"
                    required />
            </div>
            <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
        </div>

        {{-- IVA y percepciones --}}
        <div class="bg-miga/50 rounded-md p-3 space-y-2">
            <div class="flex items-center justify-between gap-4">
                <x-input-label value="Alícuota IVA" class="mb-0" />
                <select name="iva_rate" x-model.number="ivaRate"
                    class="border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm w-28">
                    <option value="0.21">21%</option>
                    <option value="0.105">10,5%</option>
                    <option value="0">0%</option>
                </select>
            </div>
            <div class="flex items-center justify-between gap-4">
                <x-input-label value="Percepción" class="mb-0" />
                <div class="flex items-center gap-1.5">
                    <input type="number" name="percepcion_rate"
                        step="0.01" min="0" max="100"
                        x-model.number="percepcionRate"
                        placeholder="0"
                        class="border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm w-24 text-right">
                    <span class="text-sm text-masa-madre">%</span>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm border-t border-miga pt-2">
                <span class="text-masa-madre">IVA $</span>
                <span class="font-mono text-masa-madre" x-text="'$ ' + fmt(ivaAmount)"></span>
            </div>
            <template x-if="percepcionRate > 0">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-masa-madre">Percepción $</span>
                    <span class="font-mono text-masa-madre" x-text="'$ ' + fmt(percepcionAmount)"></span>
                </div>
            </template>
            <div class="flex items-center justify-between text-sm border-t border-miga pt-2">
                <span class="font-medium text-corteza" x-text="percepcionRate > 0 ? 'Total c/IVA+Perc.' : 'Subtotal c/IVA'"></span>
                <span class="font-mono font-medium text-corteza" x-text="'$ ' + fmt(subtotalTotal)"></span>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar cambios</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'line-edit')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

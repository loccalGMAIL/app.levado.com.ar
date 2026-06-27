<x-crud-modal name="add-line" title="Agregar renglón" :show="$errorsInAddLine">
    <form method="POST" action="{{ route('purchases.lines.store', $purchase) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="add-line">

        <div>
            <x-input-label value="Descripción" />
            <x-text-input name="raw_name" type="text"
                class="mt-1 block w-full"
                :value="old('raw_name')"
                required />
            <x-input-error :messages="$errors->get('raw_name')" class="mt-1" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label value="Cantidad" />
                <x-text-input name="quantity_purchased" type="number"
                    step="0.0001" min="0.0001"
                    data-maxdecimals="4"
                    class="mt-1 block w-full"
                    :value="old('quantity_purchased')"
                    required />
                <x-input-error :messages="$errors->get('quantity_purchased')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Unidad" />
                <select name="purchase_unit" required
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    @foreach($units as $unit)
                        <option value="{{ $unit->value }}" @selected(old('purchase_unit') === $unit->value)>
                            {{ $unit->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('purchase_unit')" class="mt-1" />
            </div>
        </div>

        @php
            $defaultIva = old('iva_rate', $purchase->default_iva_rate !== null ? (string) $purchase->default_iva_rate : '0.21');
            $defaultPercepcion = old('percepcion_rate', $purchase->default_percepcion_rate !== null ? (string) $purchase->default_percepcion_rate : '');
        @endphp

        <div
            x-data="{
                qty: 0,
                price: 0,
                ivaRate: {{ (float) $defaultIva }},
                percepcionRate: {{ (float) ($defaultPercepcion ?: 0) }},
                get net()              { return this.qty * this.price; },
                get ivaAmount()        { return this.net * this.ivaRate; },
                get percepcionAmount() { return this.net * (this.percepcionRate / 100); },
                get lineTotal()        { return this.net * (1 + this.ivaRate + this.percepcionRate / 100); },
                fmt(n) { return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
            }"
            class="space-y-3">

            <div>
                <x-input-label value="Precio unitario" />
                <div class="relative mt-1">
                    <span class="absolute inset-y-0 left-3 flex items-center text-masa-madre text-sm">$</span>
                    <x-text-input name="unit_price" type="number"
                        step="0.0001" min="0"
                        data-maxdecimals="4"
                        class="block w-full pl-7"
                        :value="old('unit_price')"
                        @input="price = parseFloat($event.target.value) || 0"
                        required />
                </div>
                <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
            </div>

            <div class="bg-miga/50 rounded-md p-3 space-y-2">
                <div class="flex items-center justify-between gap-4">
                    <x-input-label value="Alícuota IVA" class="mb-0" />
                    <select name="iva_rate" x-model.number="ivaRate"
                        class="border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm w-28">
                        <option value="0.21" @selected($defaultIva === '0.21')>21%</option>
                        <option value="0.105" @selected($defaultIva === '0.105' || $defaultIva === '0.1050')>10,5%</option>
                        <option value="0" @selected($defaultIva === '0' || $defaultIva === '0.0000')>0%</option>
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
                <template x-if="net > 0">
                    <div class="border-t border-miga pt-2 space-y-1">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-masa-madre">Subtotal</span>
                            <span class="font-mono text-masa-madre" x-text="'$ ' + fmt(net)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-masa-madre">IVA $</span>
                            <span class="font-mono text-masa-madre" x-text="'$ ' + fmt(ivaAmount)"></span>
                        </div>
                        <template x-if="percepcionRate > 0">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-masa-madre">Percepción $</span>
                                <span class="font-mono text-masa-madre" x-text="'$ ' + fmt(percepcionAmount)"></span>
                            </div>
                        </template>
                        <div class="flex items-center justify-between text-sm border-t border-miga pt-1">
                            <span class="font-medium text-corteza">Total c/IVA</span>
                            <span class="font-mono font-medium text-corteza" x-text="'$ ' + fmt(lineTotal)"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Agregando…">Agregar renglón</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'add-line')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

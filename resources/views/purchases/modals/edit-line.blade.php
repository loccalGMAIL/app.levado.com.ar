<x-crud-modal name="line-edit" title="Editar ítem" :show="$errorsInEditLine">
    <form method="POST" :action="'/purchases/{{ $purchase->id }}/lines/' + editingLine?.id" class="space-y-4"
        x-data="purchaseLine()"
        x-effect="seedLine(editingLine)">
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
                    step="0.01" min="0.01"
                    data-maxdecimals="2"
                    class="mt-1 block w-full"
                    x-model.number="qty"
                    required />
                <x-input-error :messages="$errors->get('quantity_purchased')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Unidad" />
                <select name="purchase_unit" required
                    @change="onUnitChange($event.target.value)"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    @foreach($units as $unit)
                        <option value="{{ $unit->value }}"
                            x-bind:selected="editingLine?.purchase_unit === '{{ $unit->value }}'">
                            {{ $unit->short() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('purchase_unit')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label>Precio por <span class="font-semibold" x-text="unitLabel"></span></x-input-label>
            <div class="relative mt-1">
                <span class="absolute inset-y-0 left-3 flex items-center text-masa-madre text-sm">$</span>
                <x-text-input name="unit_price" type="number"
                    step="0.0001" min="0"
                    data-maxdecimals="4"
                    class="block w-full pl-7"
                    x-model.number="price"
                    x-on:input="onPriceInput()"
                    required />
            </div>
            <x-unit-price-hint />
            <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
        </div>

        {{-- Sin cargo: obsequio o promoción de la distribuidora --}}
        <label class="flex items-start gap-2 cursor-pointer select-none">
            {{-- Hidden previo: un checkbox destildado no viaja, y sin el 0 explícito
                 el servidor volvería a inferir la bonificación desde el precio. --}}
            <input type="hidden" name="is_bonus" value="0">
            <input type="checkbox" name="is_bonus" value="1"
                x-model="isBonus" @change="bonusTouched = true"
                class="mt-0.5 rounded border-gray-300 text-corteza focus:ring-horno">
            <span class="text-sm text-masa-madre">
                <span class="font-medium text-corteza">Sin cargo (bonificación)</span>
                <span class="block text-xs">Suma al stock sin modificar el costo del insumo ni los precios de las recetas.</span>
            </span>
        </label>

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
                <span class="font-mono font-medium text-corteza" x-text="'$ ' + fmt(lineTotal)"></span>
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

<x-crud-modal name="credit-note-add-line" title="Agregar renglón" :show="$errorsInAddLine">
    <form method="POST" action="{{ route('credit-notes.lines.store', $creditNote) }}" class="space-y-4"
        x-data="{
            purchaseLineId: '{{ old('purchase_line_id') }}',
            affectsStock: {{ old('affects_stock', old('purchase_line_id') ? '1' : '0') === '1' || old('purchase_line_id') ? 'true' : 'false' }},
            onPurchaseLineChange(select) {
                this.purchaseLineId = select.value;
                if (!select.value) { this.affectsStock = false; return; }
                const opt = select.selectedOptions[0];
                this.affectsStock = true;
                this.$refs.unit.value = opt.dataset.unit;
                this.$refs.unitPrice.value = opt.dataset.unitPrice;
                this.$refs.quantity.max = opt.dataset.quantity;
                this.$refs.quantity.placeholder = 'Máx. ' + opt.dataset.quantity;
            }
        }">
        @csrf
        <input type="hidden" name="_form" value="credit-note-add-line">

        @if($availablePurchaseLines->isNotEmpty())
            <div>
                <x-input-label for="add_cnl_purchase_line" value="Renglón de la compra de origen" />
                <select id="add_cnl_purchase_line" name="purchase_line_id"
                    @change="onPurchaseLineChange($event.target)"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    <option value="">— Renglón libre (reconocimiento económico) —</option>
                    @foreach($availablePurchaseLines as $purchaseLine)
                        <option value="{{ $purchaseLine->id }}"
                            data-unit="{{ $purchaseLine->purchase_unit->value }}"
                            data-unit-price="{{ (float) $purchaseLine->unit_price }}"
                            data-quantity="{{ (float) $purchaseLine->quantity_purchased }}"
                            @selected(old('purchase_line_id') == $purchaseLine->id)>
                            {{ $purchaseLine->raw_name }} ({{ number_format($purchaseLine->quantity_purchased, 2, ',', '.') }} {{ $purchaseLine->purchase_unit->short() }})
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('purchase_line_id')" class="mt-1" />
            </div>
        @endif

        <div x-show="!purchaseLineId" x-cloak>
            <x-input-label value="Descripción" />
            <x-text-input name="description" type="text"
                class="mt-1 block w-full"
                :value="old('description')" />
            <x-input-error :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label value="Cantidad" />
                <x-text-input name="quantity" type="number"
                    x-ref="quantity"
                    step="0.0001" min="0.0001"
                    class="mt-1 block w-full"
                    :value="old('quantity')"
                    required />
                <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Unidad" />
                <select name="unit" x-ref="unit" required
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    @foreach($units as $unit)
                        <option value="{{ $unit->value }}" @selected(old('unit') === $unit->value)>
                            {{ $unit->short() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unit')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label value="Precio unitario" />
            <div class="relative mt-1">
                <span class="absolute inset-y-0 left-3 flex items-center text-masa-madre text-sm">$</span>
                <x-text-input name="unit_price" type="number"
                    x-ref="unitPrice"
                    step="0.0001" min="0"
                    class="block w-full pl-7"
                    :value="old('unit_price')"
                    required />
            </div>
            <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
        </div>

        <label class="flex items-start gap-2 cursor-pointer select-none">
            <input type="hidden" name="affects_stock" value="0">
            <input type="checkbox" name="affects_stock" value="1"
                x-model="affectsStock" :disabled="!purchaseLineId"
                class="mt-0.5 rounded border-gray-300 text-corteza focus:ring-horno disabled:opacity-50">
            <span class="text-sm text-masa-madre">
                <span class="font-medium text-corteza">Descuenta stock</span>
                <span class="block text-xs">
                    Marcado, revierte proporcionalmente la entrada de stock del renglón de compra elegido
                    (mercadería que no vino). Destildado, es un reconocimiento puramente económico (por ejemplo,
                    rotura ya ajustada por recuento) que no toca existencias.
                </span>
            </span>
        </label>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Agregando…">Agregar renglón</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'credit-note-add-line')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

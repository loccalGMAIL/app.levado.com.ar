<x-crud-modal name="stock-count" title="Recuento físico" :show="old('_form') === 'stock-count' && $errors->any()">
    <form method="POST"
        :action="`/stock/${selected.type}/${selected.id}/counts`"
        x-data="{ counted: {{ Js::from(old('_form') === 'stock-count' ? old('counted_quantity', '') : '') }} }"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="stock-count">
        <input type="hidden" name="stock_type" x-bind:value="selected.type">
        <input type="hidden" name="stock_id" x-bind:value="selected.id">
        <input type="hidden" name="stock_name" x-bind:value="selected.name">
        <input type="hidden" name="stock_unit" x-bind:value="selected.unit">
        <input type="hidden" name="stock_qty" x-bind:value="selected.qty">

        <p class="text-sm text-masa-madre">
            <span class="font-medium text-corteza" x-text="selected.name"></span>
            — stock según sistema: <span class="font-mono" x-text="Number(selected.qty).toLocaleString('es-AR', {maximumFractionDigits: 2})"></span>
            <span x-text="selected.unit"></span>
        </p>

        <div>
            <x-input-label for="count_quantity" value="Cantidad contada" />
            <x-text-input id="count_quantity" name="counted_quantity" type="number" step="any" min="0"
                class="mt-1 block w-full"
                x-model="counted"
                required />
            <x-input-error :messages="old('_form') === 'stock-count' ? $errors->get('counted_quantity') : []" class="mt-2" />
        </div>

        {{-- Delta calculado en vivo antes de confirmar --}}
        <div class="rounded-md bg-miga/60 px-3 py-2 text-sm" x-show="counted !== ''" style="display: none;">
            <template x-if="(parseFloat(counted) - selected.qty) > 0">
                <p class="text-green-700">
                    Diferencia: <span class="font-mono font-medium" x-text="'+' + (parseFloat(counted) - selected.qty).toLocaleString('es-AR', {maximumFractionDigits: 2})"></span>
                    <span x-text="selected.unit"></span> (se sumará al stock)
                </p>
            </template>
            <template x-if="(parseFloat(counted) - selected.qty) < 0">
                <p class="text-red-700">
                    Diferencia: <span class="font-mono font-medium" x-text="(parseFloat(counted) - selected.qty).toLocaleString('es-AR', {maximumFractionDigits: 2})"></span>
                    <span x-text="selected.unit"></span> (se descontará del stock)
                </p>
            </template>
            <template x-if="(parseFloat(counted) - selected.qty) === 0">
                <p class="text-masa-madre">Sin diferencia: el stock ya coincide.</p>
            </template>
        </div>

        <div class="flex justify-end gap-3">
            <x-secondary-button type="button" @click="$dispatch('close-modal', 'stock-count')">Cancelar</x-secondary-button>
            <x-primary-button data-loading="Guardando…">Confirmar recuento</x-primary-button>
        </div>
    </form>
</x-crud-modal>

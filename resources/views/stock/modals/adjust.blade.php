<x-crud-modal name="stock-adjust" title="Ajuste de stock" :show="old('_form') === 'stock-adjust' && $errors->any()">
    <form method="POST"
        :action="`/stock/${selected.type}/${selected.id}/adjustments`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="stock-adjust">
        <input type="hidden" name="stock_type" x-bind:value="selected.type">
        <input type="hidden" name="stock_id" x-bind:value="selected.id">
        <input type="hidden" name="stock_name" x-bind:value="selected.name">
        <input type="hidden" name="stock_unit" x-bind:value="selected.unit">
        <input type="hidden" name="stock_qty" x-bind:value="selected.qty">

        <p class="text-sm text-masa-madre">
            <span class="font-medium text-corteza" x-text="selected.name"></span>
            — stock actual: <span class="font-mono" x-text="Number(selected.qty).toLocaleString('es-AR', {maximumFractionDigits: 2})"></span>
            <span x-text="selected.unit"></span>
        </p>

        <div>
            <x-input-label for="adjust_quantity" value="Cantidad" />
            <x-text-input id="adjust_quantity" name="quantity" type="number" step="any"
                class="mt-1 block w-full"
                :value="old('_form') === 'stock-adjust' ? old('quantity') : ''"
                required />
            <p class="mt-1 text-xs text-masa-madre">Positivo suma stock, negativo resta (ej: -2,5).</p>
            <x-input-error :messages="old('_form') === 'stock-adjust' ? $errors->get('quantity') : []" class="mt-2" />
        </div>

        <div>
            <x-input-label for="adjust_reason" value="Motivo" />
            <x-text-input id="adjust_reason" name="reason" type="text"
                class="mt-1 block w-full"
                :value="old('_form') === 'stock-adjust' ? old('reason') : ''"
                placeholder="Ej: rotura de bolsa, corrección de carga"
                required />
            <x-input-error :messages="old('_form') === 'stock-adjust' ? $errors->get('reason') : []" class="mt-2" />
        </div>

        <div class="flex justify-end gap-3">
            <x-secondary-button type="button" @click="$dispatch('close-modal', 'stock-adjust')">Cancelar</x-secondary-button>
            <x-primary-button data-loading="Guardando…">Registrar ajuste</x-primary-button>
        </div>
    </form>
</x-crud-modal>

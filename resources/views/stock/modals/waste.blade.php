<x-crud-modal name="stock-waste" title="Registrar merma" :show="old('_form') === 'stock-waste' && $errors->any()">
    <form method="POST"
        :action="`/stock/${selected.type}/${selected.id}/wastes`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="stock-waste">
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
            <x-input-label for="waste_quantity" value="Cantidad perdida" />
            <x-text-input id="waste_quantity" name="quantity" type="number" step="any" min="0"
                class="mt-1 block w-full"
                :value="old('_form') === 'stock-waste' ? old('quantity') : ''"
                required />
            <x-input-error :messages="old('_form') === 'stock-waste' ? $errors->get('quantity') : []" class="mt-2" />
        </div>

        <div>
            <x-input-label for="waste_reason" value="Motivo" />
            <x-text-input id="waste_reason" name="reason" type="text"
                class="mt-1 block w-full"
                :value="old('_form') === 'stock-waste' ? old('reason') : ''"
                placeholder="Ej: vencido, se quemó la tanda"
                required />
            <x-input-error :messages="old('_form') === 'stock-waste' ? $errors->get('reason') : []" class="mt-2" />
        </div>

        <div class="flex justify-end gap-3">
            <x-secondary-button type="button" @click="$dispatch('close-modal', 'stock-waste')">Cancelar</x-secondary-button>
            <x-primary-button data-loading="Guardando…">Registrar merma</x-primary-button>
        </div>
    </form>
</x-crud-modal>

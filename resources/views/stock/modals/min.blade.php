<x-crud-modal name="stock-min" title="Stock mínimo" :show="old('_form') === 'stock-min' && $errors->any()">
    <form method="POST"
        :action="`/stock/${selected.type}/${selected.id}/min`"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_method" value="PATCH">
        <input type="hidden" name="_form" value="stock-min">
        <input type="hidden" name="stock_type" x-bind:value="selected.type">
        <input type="hidden" name="stock_id" x-bind:value="selected.id">
        <input type="hidden" name="stock_name" x-bind:value="selected.name">
        <input type="hidden" name="stock_unit" x-bind:value="selected.unit">
        <input type="hidden" name="stock_qty" x-bind:value="selected.qty">

        <p class="text-sm text-masa-madre">
            Cuando el stock de <span class="font-medium text-corteza" x-text="selected.name"></span>
            quede en o por debajo del mínimo se muestra una alerta en el listado.
        </p>

        <div>
            <x-input-label for="min_quantity" value="Cantidad mínima" />
            <x-text-input id="min_quantity" name="min_quantity" type="number" step="any" min="0"
                class="mt-1 block w-full"
                x-bind:value="{{ old('_form') === 'stock-min' ? Js::from(old('min_quantity')) : 'selected.min' }}" />
            <p class="mt-1 text-xs text-masa-madre">Dejalo vacío para desactivar la alerta de mínimo.</p>
            <x-input-error :messages="old('_form') === 'stock-min' ? $errors->get('min_quantity') : []" class="mt-2" />
        </div>

        <div class="flex justify-end gap-3">
            <x-secondary-button type="button" @click="$dispatch('close-modal', 'stock-min')">Cancelar</x-secondary-button>
            <x-primary-button data-loading="Guardando…">Guardar mínimo</x-primary-button>
        </div>
    </form>
</x-crud-modal>

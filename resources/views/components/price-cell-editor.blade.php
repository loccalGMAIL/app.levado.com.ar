{{--
    Editor de política de precio (popover). Se monta UNA sola vez en el layout
    autenticado: escucha `edit-price` (lo despachan las celdas x-data="priceCell")
    y se posiciona con coordenadas fixed (priceEditor.popStyle) sobre el ancla de
    la celda, para no quedar recortado por el overflow-x-auto de las tablas.
    Al guardar avisa con `price-saved` y la celda de esa url se actualiza sola.
--}}
<div x-data="priceEditor" @edit-price.window="openFor($event.detail)"
     x-show="open" style="display: none;" @keydown.escape.window="open && cancel()">
    {{-- Backdrop: cierra al hacer clic fuera; captura el clic sin tapar visualmente. --}}
    <div class="fixed inset-0 z-40" @click="cancel()"></div>

    <div :style="popStyle" x-transition.opacity
         class="z-50 w-60 bg-white border border-miga rounded-lg shadow-lg p-3 text-left font-sans">
        <div class="flex flex-col gap-2">
            <label class="text-[11px] font-medium text-masa-madre uppercase tracking-wide">Política de precio</label>

            <select x-model="draftType"
                    @change="$nextTick(() => { const el = draftType === 'manual' ? $refs.priceInput : $refs.valueInput; el?.focus(); el?.select?.(); })"
                    class="w-full text-sm border-gray-300 rounded-md focus:border-horno focus:ring-horno">
                <option value="manual">Manual</option>
                <option value="margin">Margen % sobre costo</option>
                <option value="markup">Recargo % sobre costo</option>
            </select>

            {{-- Manual: precio a mano --}}
            <div x-show="draftType === 'manual'">
                <div class="relative">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-masa-madre">$</span>
                    <input x-ref="priceInput" type="number" step="0.01" min="0" x-model="draftPrice"
                           @keydown.enter.prevent="save()"
                           placeholder="Precio"
                           class="w-full pl-6 text-sm text-right border-gray-300 rounded-md focus:border-horno focus:ring-horno font-mono">
                </div>
                <p class="text-[10px] text-masa-madre mt-1">Vacío borra el precio.</p>
            </div>

            {{-- Margen / Recargo: porcentaje --}}
            <div x-show="draftType !== 'manual'">
                <div class="relative">
                    <input x-ref="valueInput" type="number" step="0.01" min="0" x-model="draftValue"
                           @keydown.enter.prevent="save()"
                           placeholder="0"
                           class="w-full pr-7 text-sm text-right border-gray-300 rounded-md focus:border-horno focus:ring-horno font-mono">
                    <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-masa-madre">%</span>
                </div>
                <p class="text-[10px] text-masa-madre mt-1" x-show="draftType === 'margin'">Precio = costo ÷ (1 − margen %)</p>
                <p class="text-[10px] text-masa-madre mt-1" x-show="draftType === 'markup'">Precio = costo × (1 + recargo %)</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-1">
                <button type="button" @click="cancel()"
                        class="text-xs text-masa-madre hover:text-corteza transition-colors">Cancelar</button>
                <button type="button" @click="save()" :disabled="saving"
                        class="text-xs px-3 py-1 bg-corteza text-white rounded hover:bg-horno transition-colors disabled:opacity-50">
                    Guardar
                </button>
            </div>
        </div>
    </div>
</div>

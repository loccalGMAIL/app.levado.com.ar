@props(['pickerId', 'products', 'showFooter' => true, 'showPrevious' => true])

{{--
    Tabla de renglones + picker de la grilla productionOrderLines — compartido
    entre partials.request-lines (dentro de un pedido ya existente) y el
    modal de alta (modo deferred, showFooter=false: el form entero se manda
    de una, no hay "Guardar pedido" propio). Se monta DENTRO de un x-data que
    ya trae lines/addProduct/removeLine/fromPrevious/etc — ver
    resources/js/production/order-lines.js.

    El picker va DEBAJO de la tabla (no arriba): con el foco en la última
    cantidad, Tab cae directo ahí — @keydown.enter.prevent="focusPicker()" en
    la cantidad cierra el ciclo elegir→cantidad→Enter→elegir sin soltar el
    teclado. x-ref="picker" es lo que usa focusPicker().

    Options server-side (no x-for): son ~195 y no cambian tras cargar la
    página — TomSelect envuelve el <select> ya completo (mismo patrón que
    los modales de Compras), Alpine sólo escucha su evento `change`.
--}}
<p x-show="duplicateNotice" x-cloak x-text="duplicateNotice" class="px-5 pt-3 text-xs text-amber-700"></p>
<p x-show="error" x-cloak x-text="error" class="px-5 pt-3 text-xs text-red-600"></p>

<table x-show="lines.length > 0" x-cloak class="w-full text-sm">
    <tbody x-ref="rows" class="divide-y divide-miga">
        <template x-for="(line, i) in lines" :key="line.id ?? ('new-' + i)">
            <tr>
                <td class="px-5 py-2 text-corteza" x-text="line.name"></td>
                <td class="px-5 py-2 text-right w-40">
                    {{-- step="any": con un paso fijo el navegador bloquea el envío del form al
                         escribir un valor fuera de grilla (2,5 en un artículo por unidad). Los
                         saltos los dan los botones y las flechas (stepQuantity), no el input. --}}
                    <div class="flex items-center justify-end gap-1">
                        <button type="button" @click="stepQuantity(line, -1)" tabindex="-1"
                            class="w-6 h-7 shrink-0 rounded border border-miga text-masa-madre hover:text-corteza hover:bg-white" title="Restar">−</button>
                        <input type="number" step="any" min="0.01" x-model="line.quantity"
                            @keydown.enter.prevent="focusPicker()"
                            @keydown.up.prevent="stepQuantity(line, 1)"
                            @keydown.down.prevent="stepQuantity(line, -1)"
                            class="w-16 text-right border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                        <button type="button" @click="stepQuantity(line, 1)" tabindex="-1"
                            class="w-6 h-7 shrink-0 rounded border border-miga text-masa-madre hover:text-corteza hover:bg-white" title="Sumar">+</button>
                    </div>
                </td>
                <td class="px-5 py-2 text-masa-madre text-xs">
                    {{-- La ayuda («= 33 u») va en la misma línea que los controles, no debajo:
                         una fila de dos renglones por artículo hacía la grilla muy alta. --}}
                    <div class="flex items-center gap-2">
                        <template x-if="productionUnitOf(line)">
                            <select x-model="line.measure"
                                class="border-gray-300 rounded-md shadow-sm text-xs py-1 focus:border-horno focus:ring-horno">
                                <option value="unit" x-text="line.unit"></option>
                                <option value="production_unit" x-text="productionUnitOf(line).label"></option>
                            </select>
                        </template>
                        <span x-show="! productionUnitOf(line)" x-text="line.unit"></span>
                        <span x-show="baseHint(line)" x-cloak x-text="baseHint(line)" class="text-[11px] whitespace-nowrap"></span>
                    </div>
                </td>
                <td class="px-5 py-2 text-right w-8">
                    <button type="button" @click="removeLine(i)" tabindex="-1" class="text-masa-madre hover:text-red-500 transition-colors" title="Quitar">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </td>
            </tr>
        </template>
    </tbody>
</table>

<div class="px-5 py-3 flex items-center justify-between gap-3 flex-wrap"
    :class="lines.length > 0 && 'border-t border-miga'">
    <select data-searchable id="{{ $pickerId }}" x-ref="picker"
        @change="addProduct($event.target.value, $event.target); $event.target.value = ''"
        class="min-w-56 flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
        <option value="">Agregar artículo…</option>
        @foreach($products as $product)
            <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
        @endforeach
    </select>
    @if($showPrevious)
        <button type="button" @click="fromPrevious()" class="text-sm text-horno hover:underline whitespace-nowrap">
            Traer del pedido anterior
        </button>
    @endif
</div>

@if($showFooter)
    <div class="px-5 py-3 flex items-center justify-end gap-3 border-t border-miga">
        <span x-show="dirty" x-cloak class="text-xs text-amber-700">Sin guardar</span>
        <button type="button" @click="save()" :disabled="saving || ! dirty"
            class="px-4 py-1.5 bg-corteza text-white text-xs rounded-md hover:bg-horno transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            <span x-show="! saving">Guardar pedido</span>
            <span x-show="saving">Guardando…</span>
        </button>
    </div>
@endif

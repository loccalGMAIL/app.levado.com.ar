{{--
    Líneas de un pedido (artículo + cantidad). Dos ramas genuinamente
    distintas — no una sola con inputs deshabilitados:
    - $showGrid: grilla editable (picker + cantidad editable + guardado en
      lote vía ProductionOrderLineController::sync()).
    - si no: tabla de sólo lectura, server-side (a propósito: assertSee()
      sobre un nombre con acentos no es confiable contra un x-for — Js::from()
      los escapa \uXXXX — así que la rama de lectura se queda renderizada por
      Blade, no por Alpine).

    $showGrid ya combina isEditable() + el permiso manage-costs (ver
    show.blade.php): un viewer, aunque la orden sea editable, ve sólo lectura.
--}}
@if($showGrid)
    <div x-data="productionOrderLines({
            saveUrl: @js(route('production-orders.requests.lines.sync', [$productionOrder, $request])),
            previousUrl: @js(route('production-orders.requests.previous-lines', [$productionOrder, $request])),
            products: products,
            lines: @js($request->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'name' => $line->product->name ?? '—',
                'unit' => $line->unit->short(),
                'quantity' => (float) $line->quantity,
            ])),
        })"
        class="border-t border-miga">
        <div class="px-5 py-3 flex items-center justify-between gap-3 flex-wrap">
            {{-- Options server-side (no x-for): son ~195 y no cambian tras cargar la
                 página — TomSelect envuelve el <select> ya completo (mismo patrón que
                 los modales de Compras), Alpine sólo escucha su evento `change`. --}}
            <select data-searchable id="lines-picker-{{ $request->id }}"
                @change="addProduct($event.target.value, $event.target); $event.target.value = ''"
                class="min-w-56 flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                <option value="">Agregar artículo…</option>
                @foreach($products as $product)
                    <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                @endforeach
            </select>
            <button type="button" @click="fromPrevious()" class="text-sm text-horno hover:underline whitespace-nowrap">
                Traer del pedido anterior
            </button>
        </div>

        <p x-show="duplicateNotice" x-cloak x-text="duplicateNotice" class="px-5 pb-2 text-xs text-amber-700"></p>
        <p x-show="error" x-cloak x-text="error" class="px-5 pb-2 text-xs text-red-600"></p>

        <table x-show="lines.length > 0" x-cloak class="w-full text-sm">
            <tbody x-ref="rows" class="divide-y divide-miga">
                <template x-for="(line, i) in lines" :key="line.id ?? ('new-' + i)">
                    <tr>
                        <td class="px-5 py-2 text-corteza" x-text="line.name"></td>
                        <td class="px-5 py-2 text-right w-28">
                            <input type="number" step="0.01" min="0.01" x-model="line.quantity"
                                class="w-full text-right border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        </td>
                        <td class="px-5 py-2 text-masa-madre text-xs" x-text="line.unit"></td>
                        <td class="px-5 py-2 text-right w-8">
                            <button type="button" @click="removeLine(i)" class="text-masa-madre hover:text-red-500 transition-colors" title="Quitar">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div class="px-5 py-3 flex items-center justify-end gap-3 border-t border-miga">
            <span x-show="dirty" x-cloak class="text-xs text-amber-700">Sin guardar</span>
            <button type="button" @click="save()" :disabled="saving || ! dirty"
                class="px-4 py-1.5 bg-corteza text-white text-xs rounded-md hover:bg-horno transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <span x-show="! saving">Guardar pedido</span>
                <span x-show="saving">Guardando…</span>
            </button>
        </div>
    </div>
@elseif($request->lines->isNotEmpty())
    <table class="w-full text-sm">
        <tbody class="divide-y divide-miga">
            @foreach($request->lines as $line)
                <tr>
                    <td class="px-5 py-2 text-corteza">{{ $line->product?->name ?? '—' }}</td>
                    <td class="px-5 py-2 text-right font-mono text-corteza">
                        {{ number_format($line->quantity, 2, ',', '.') }} {{ $line->unit->short() }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

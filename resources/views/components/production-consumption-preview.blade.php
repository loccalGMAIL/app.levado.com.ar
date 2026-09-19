{{--
    Tabla de insumos a consumir (orden y orden instantánea). Presentacional
    puro: se monta DENTRO de un x-data que trae `previewLines`, `materialCost`,
    `laborCost`, `totalCost`, `loading`, `error`, `hasShortfall`, `fmt()` y
    `fmtQty()` — el shape que arma consumptionPreviewState() (ver
    resources/js/production/consumption-preview.js). Mismo contrato que
    <x-price-cell-editor>: el componente no define su propio x-data.

    `previewLines`, no `lines`: la orden instantánea combina este x-data con
    productionOrderLines() (artículos del pedido) en el mismo <form> — ambos
    factories usaban "lines" y colisionaban.
--}}
@props([
    'shortfallNote' => 'Algún insumo no alcanza: producir igual descuenta lo que hay y deja el stock en negativo.',
    'emptyNote' => null,
])

<div class="bg-white border border-miga rounded-lg shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-miga flex items-center justify-between">
        <h3 class="text-sm font-semibold text-corteza">Insumos a consumir</h3>
        <span x-show="loading" class="text-xs text-masa-madre">Calculando…</span>
    </div>

    <template x-if="error">
        <p class="px-5 py-4 text-sm text-red-600" x-text="error"></p>
    </template>

    @if($emptyNote)
        <template x-if="! error && previewLines.length === 0 && ! loading">
            <p class="px-5 py-4 text-sm text-masa-madre">{{ $emptyNote }}</p>
        </template>
    @endif

    <template x-if="! error && previewLines.length > 0">
        <div>
            <div x-show="hasShortfall" class="px-5 py-2.5 bg-amber-50 border-b border-amber-100 text-xs text-amber-700">
                {{ $shortfallNote }}
            </div>
            <table class="w-full text-sm">
                <thead class="bg-miga text-masa-madre">
                    <tr>
                        <th class="px-5 py-2 text-left font-medium">Insumo</th>
                        <th class="px-5 py-2 text-right font-medium">Necesario</th>
                        <th class="px-5 py-2 text-right font-medium">Disponible</th>
                        <th class="px-5 py-2 text-right font-medium">Costo</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-miga">
                    <template x-for="line in previewLines" :key="line.type + '-' + line.id">
                        <tr :class="line.shortfall > 0 ? 'bg-amber-50/50' : ''">
                            <td class="px-5 py-2 text-corteza" x-text="line.name"></td>
                            <td class="px-5 py-2 text-right font-mono text-corteza">
                                <span x-text="fmtQty(line.quantity)"></span> <span class="text-masa-madre" x-text="line.unit"></span>
                            </td>
                            <td class="px-5 py-2 text-right font-mono" :class="line.shortfall > 0 ? 'text-amber-600' : 'text-masa-madre'">
                                <span x-text="fmtQty(line.available)"></span>
                            </td>
                            <td class="px-5 py-2 text-right font-mono text-masa-madre">
                                <span x-text="fmt(line.line_cost)"></span>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot class="border-t border-miga">
                    <tr>
                        <td colspan="3" class="px-5 py-1.5 text-right text-sm text-masa-madre">Costo de insumos</td>
                        <td class="px-5 py-1.5 text-right font-mono text-corteza">$ <span x-text="fmt(materialCost)"></span></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-5 py-1.5 text-right text-sm text-masa-madre">Mano de obra</td>
                        <td class="px-5 py-1.5 text-right font-mono text-corteza">$ <span x-text="fmt(laborCost)"></span></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-5 py-2.5 text-right text-sm text-masa-madre">Costo total</td>
                        <td class="px-5 py-2.5 text-right font-mono text-corteza font-semibold">$ <span x-text="fmt(totalCost)"></span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </template>
</div>

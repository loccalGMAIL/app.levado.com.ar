<x-app-layout>
    <x-slot name="title">Orden de producción</x-slot>

    @php
        $editable = ! $productionOrder->isDone() && ! $productionOrder->isCancelled();
    @endphp

    <div class="py-8 px-6 lg:px-8 max-w-4xl mx-auto"
        x-data="{
            loading: false,
            error: '',
            lines: [],
            totalCost: 0,
            get hasShortfall() { return this.lines.some(l => l.shortfall > 0); },
            async loadPreview() {
                this.error = '';
                this.loading = true;
                try {
                    const res = await fetch('{{ route('production-orders.preview', $productionOrder) }}', {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (! res.ok) { this.lines = []; this.totalCost = 0; this.error = 'No se pudo calcular el consumo de insumos.'; return; }
                    const data = await res.json();
                    this.lines = data.lines;
                    this.totalCost = data.total_cost;
                } catch (e) {
                    this.error = 'No se pudo calcular el consumo de insumos.';
                } finally {
                    this.loading = false;
                }
            },
            fmt(n) { return new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0); },
            fmtQty(n) { return new Intl.NumberFormat('es-AR', { maximumFractionDigits: 3 }).format(n || 0); },
        }"
        x-init="loadPreview()">

        <div class="mb-5">
            <a href="{{ route('production-orders.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">← Órdenes de producción</a>
            <div class="flex items-center gap-3 mt-2">
                <x-production-order-type-badge :type="$productionOrder->type" />
                <x-production-order-status-badge :status="$productionOrder->status" />
                <span class="text-sm text-masa-madre">{{ $productionOrder->scheduled_for?->format('d/m/Y') ?? 'Sin fecha' }}</span>
            </div>
            @if($productionOrder->notes)
                <p class="text-sm text-masa-madre mt-2">{{ $productionOrder->notes }}</p>
            @endif
            <a href="{{ route('production-orders.delivery-sheet', $productionOrder) }}" class="text-sm text-horno hover:underline mt-2 inline-block">
                Planilla de reparto →
            </a>
        </div>

        {{-- Repetir / guardar como plantilla --}}
        @can('manage-costs')
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <form method="POST" action="{{ route('production-orders.duplicate', $productionOrder) }}" class="flex items-center gap-2">
                    @csrf
                    <input type="date" name="scheduled_for" value="{{ now()->toDateString() }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <button type="submit" class="px-3 py-1.5 border border-miga text-xs text-masa-madre rounded-md hover:bg-miga transition-colors">Repetir orden</button>
                </form>
                <button type="button" @click="$dispatch('open-modal', 'production-order-save-template')"
                    class="px-3 py-1.5 border border-miga text-xs text-masa-madre rounded-md hover:bg-miga transition-colors">
                    Guardar como plantilla
                </button>
            </div>
        @endcan

        {{-- Acciones de estado --}}
        @can('manage-costs')
            <div class="flex flex-wrap items-center gap-2 mb-6">
                @if($productionOrder->isDraft())
                    <form method="POST" action="{{ route('production-orders.transition', $productionOrder) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="confirmed">
                        <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">Confirmar</button>
                    </form>
                @endif

                @if($productionOrder->status === \App\Enums\ProductionOrderStatus::Confirmed)
                    <form method="POST" action="{{ route('production-orders.transition', $productionOrder) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="draft">
                        <button type="submit" class="px-4 py-2 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">Volver a borrador</button>
                    </form>
                    <form method="POST" action="{{ route('production-orders.transition', $productionOrder) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="in_production">
                        <button type="submit" class="px-4 py-2 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">Marcar en producción</button>
                    </form>
                @endif

                @if(in_array($productionOrder->status, [\App\Enums\ProductionOrderStatus::Confirmed, \App\Enums\ProductionOrderStatus::InProduction], true))
                    <form method="POST" action="{{ route('production-orders.produce', $productionOrder) }}"
                        onsubmit="return confirm('¿Producir esta orden? Se descuentan los insumos y se suma el stock de cada artículo.');">
                        @csrf
                        <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">Producir</button>
                    </form>
                @endif

                @unless($productionOrder->isCancelled())
                    <form method="POST" action="{{ route('production-orders.cancel', $productionOrder) }}"
                        onsubmit="return confirm('¿Anular esta orden? Si ya se produjo, se revierte el stock.');" class="ml-auto">
                        @csrf @method('PATCH')
                        <button type="submit" class="px-4 py-2 text-sm text-red-500 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors">Anular orden</button>
                    </form>
                @endunless
            </div>
        @endcan

        {{-- Pedidos --}}
        <div class="space-y-4 mb-8">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-corteza">Pedidos</h3>
                @can('manage-costs')
                    @if($editable)
                        <button type="button" @click="$dispatch('open-modal', 'production-order-request-create')"
                            class="text-sm text-horno hover:underline">+ Agregar pedido</button>
                    @endif
                @endcan
            </div>

            @if($productionOrder->productionOrderRequests->isEmpty())
                <x-empty-state>Todavía no hay pedidos en esta orden.</x-empty-state>
            @else
                @foreach($productionOrder->productionOrderRequests as $request)
                    <div class="bg-white border border-miga rounded-lg shadow-sm">
                        <div class="px-5 py-3 border-b border-miga flex items-center justify-between">
                            <div>
                                <span class="text-xs text-masa-madre">{{ $request->destination_type->label() }}</span>
                                <div class="font-medium text-corteza text-sm">{{ $request->destination?->name ?? '—' }}</div>
                                @if($request->notes)
                                    <p class="text-xs text-masa-madre mt-0.5">{{ $request->notes }}</p>
                                @endif
                            </div>
                            @can('manage-costs')
                                @if($editable)
                                    <form method="POST" action="{{ route('production-orders.requests.destroy', [$productionOrder, $request]) }}"
                                        onsubmit="return confirm('¿Eliminar este pedido y sus artículos?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors p-1" title="Eliminar pedido">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </div>

                        @if($request->lines->isNotEmpty())
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-miga">
                                    @foreach($request->lines as $line)
                                        <tr>
                                            <td class="px-5 py-2 text-corteza">{{ $line->product?->name ?? '—' }}</td>
                                            <td class="px-5 py-2 text-right font-mono text-corteza">
                                                {{ number_format($line->quantity, 2, ',', '.') }} {{ $line->unit->short() }}
                                            </td>
                                            @can('manage-costs')
                                                @if($editable)
                                                    <td class="px-5 py-2 text-right w-8">
                                                        <form method="POST" action="{{ route('production-orders.requests.lines.destroy', [$productionOrder, $request, $line]) }}">
                                                            @csrf @method('DELETE')
                                                            <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors" title="Quitar">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </td>
                                                @endif
                                            @endcan
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        @can('manage-costs')
                            @if($editable)
                                <form method="POST" action="{{ route('production-orders.requests.lines.store', [$productionOrder, $request]) }}"
                                    class="px-5 py-3 border-t border-miga flex items-center gap-2">
                                    @csrf
                                    <select name="product_id" required
                                        class="flex-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                        <option value="">Elegí un artículo…</option>
                                        @foreach($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" min="0.01" name="quantity" required placeholder="Cantidad"
                                        class="w-28 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                    <button type="submit" class="px-3 py-1.5 bg-corteza text-white text-xs rounded-md hover:bg-horno transition-colors">Agregar</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Resumen agregado --}}
        @if($aggregated->isNotEmpty())
            <div class="bg-white border border-miga rounded-lg shadow-sm overflow-hidden mb-6">
                <div class="px-5 py-3 border-b border-miga">
                    <h3 class="text-sm font-semibold text-corteza">Total a producir</h3>
                </div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-miga">
                        @foreach($aggregated as $entry)
                            <tr>
                                <td class="px-5 py-2 text-corteza">{{ $entry['product']->name }}</td>
                                <td class="px-5 py-2 text-right font-mono text-corteza">
                                    {{ number_format($entry['quantity'], 2, ',', '.') }} {{ $entry['product']->unit->short() }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Preview de insumos --}}
            <div class="bg-white border border-miga rounded-lg shadow-sm overflow-hidden mb-6">
                <div class="px-5 py-3 border-b border-miga flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-corteza">Insumos a consumir</h3>
                    <span x-show="loading" class="text-xs text-masa-madre">Calculando…</span>
                </div>

                <template x-if="error">
                    <p class="px-5 py-4 text-sm text-red-600" x-text="error"></p>
                </template>

                <template x-if="! error && lines.length > 0">
                    <div>
                        <div x-show="hasShortfall" class="px-5 py-2.5 bg-amber-50 border-b border-amber-100 text-xs text-amber-700">
                            Algún insumo no alcanza: producir igual descuenta lo que hay y deja el stock en negativo.
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
                                <template x-for="line in lines" :key="line.type + '-' + line.id">
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
                                    <td colspan="3" class="px-5 py-2.5 text-right text-sm text-masa-madre">Costo total de insumos</td>
                                    <td class="px-5 py-2.5 text-right font-mono text-corteza font-semibold">$ <span x-text="fmt(totalCost)"></span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </template>
            </div>
        @endif

        @can('manage-costs')
            @if($editable)
                @include('production-orders.modals.request')
            @endif
            @include('production-orders.modals.save-template')
        @endcan
    </div>
</x-app-layout>

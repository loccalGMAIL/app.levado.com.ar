<x-app-layout>
    <x-slot name="title">Orden de producción</x-slot>

    @php
        $editable = $productionOrder->isEditable();
        // La grilla exige poder editar Y el permiso de escritura — un viewer
        // ve sólo lectura aunque la orden siga editable (ver partials.request-lines).
        $showGrid = $editable && auth()->user()->can('manage-costs');
    @endphp

    <div class="py-8 px-6 lg:px-8 max-w-4xl mx-auto"
        x-data="{
            ...consumptionPreviewState(),
            products: @js($products),
            aggregated: [],
            async loadPreview() {
                this.error = '';
                this.loading = true;
                try {
                    const res = await fetch('{{ route('production-orders.preview', $productionOrder) }}', {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (! res.ok) { this.resetPreview(); this.aggregated = []; this.error = 'No se pudo calcular el consumo de insumos.'; return; }
                    const data = await res.json();
                    this.applyPreview(data);
                    this.aggregated = data.aggregated;
                } catch (e) {
                    this.error = 'No se pudo calcular el consumo de insumos.';
                } finally {
                    this.loading = false;
                }
            },
        }"
        x-init="loadPreview()"
        @@production-lines-saved.window="applyPreview($event.detail.preview); aggregated = $event.detail.aggregated">

        <div class="mb-5">
            <a href="{{ route('production-orders.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">← Órdenes de producción</a>

            <div class="flex flex-wrap items-center gap-3 mt-2">
                <h2 class="text-base font-semibold text-corteza">{{ $productionOrder->numberLabel() }}</h2>
                <x-production-order-type-badge :type="$productionOrder->type" />
                <x-production-order-status-badge :status="$productionOrder->status" />
                <span class="text-sm text-masa-madre">{{ $productionOrder->scheduled_for?->format('d/m/Y') ?? 'Sin fecha' }}</span>

                <div class="flex flex-wrap items-center gap-2 ml-auto">
                    @can('manage-costs')
                        @if($editable)
                            <button type="button" @click="$dispatch('open-modal', 'production-order-request-create')"
                                class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                                + Nuevo pedido
                            </button>
                        @endif
                    @endcan

                    <a href="{{ route('production-orders.production-sheet', $productionOrder) }}"
                        class="px-4 py-2 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">
                        Planilla de producción
                    </a>

                    <a href="{{ route('production-orders.delivery-sheet', $productionOrder) }}"
                        class="px-4 py-2 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">
                        Planilla de reparto
                    </a>

                    @can('manage-costs')
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
                                onsubmit="return confirm('¿Anular esta orden? Si ya se produjo, se revierte el stock.');">
                                @csrf @method('PATCH')
                                <button type="submit" class="px-4 py-2 border border-red-200 text-sm text-red-600 rounded-md hover:bg-red-50 hover:text-red-700 transition-colors">Anular</button>
                            </form>
                        @endunless
                    @endcan
                </div>
            </div>

            @if($productionOrder->notes)
                <p class="text-sm text-masa-madre mt-2">{{ $productionOrder->notes }}</p>
            @endif
        </div>

        {{-- Pedidos: acordeón, cerrado por default — el encabezado sólo
             muestra número, destino y cantidad de artículos; el detalle
             (notas, grilla, eliminar) vive adentro de x-show="open". --}}
        <div class="space-y-4 mb-8">
            <h3 class="text-sm font-semibold text-corteza">Pedidos</h3>

            @if($productionOrder->productionOrderRequests->isEmpty())
                <x-empty-state>Todavía no hay pedidos en esta orden.</x-empty-state>
            @else
                @foreach($productionOrder->productionOrderRequests as $request)
                    <div class="bg-white border border-miga rounded-lg shadow-sm" x-data="{ open: false }">
                        <button type="button" @click="open = ! open"
                            class="w-full px-5 py-3 flex items-center justify-between gap-3 text-left"
                            :class="open && 'border-b border-miga'">
                            <div class="flex items-center gap-3 min-w-0">
                                <svg class="w-3 h-3 shrink-0 text-masa-madre transition-transform" :class="open ? '' : '-rotate-90'"
                                    fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                                <div class="min-w-0">
                                    <span class="text-xs text-masa-madre">{{ $request->numberLabel() }} · {{ $request->destination_type->label() }}</span>
                                    <div class="font-medium text-corteza text-sm truncate">{{ $request->destination?->name ?? '—' }}</div>
                                </div>
                            </div>
                            <span class="text-xs text-masa-madre shrink-0">
                                {{ $request->lines->count() }} {{ $request->lines->count() === 1 ? 'artículo' : 'artículos' }}
                            </span>
                        </button>

                        <div x-show="open" x-cloak>
                            @if($request->notes)
                                <p class="px-5 pt-3 text-xs text-masa-madre">{{ $request->notes }}</p>
                            @endif

                            @can('manage-costs')
                                @if($editable)
                                    <div class="px-5 pt-3 flex justify-end">
                                        <form method="POST" action="{{ route('production-orders.requests.destroy', [$productionOrder, $request]) }}"
                                            onsubmit="return confirm('¿Eliminar este pedido y sus artículos?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-masa-madre hover:text-red-500 transition-colors p-1" title="Eliminar pedido">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            @endcan

                            @include('production-orders.partials.request-lines', ['productionOrder' => $productionOrder, 'request' => $request, 'showGrid' => $showGrid])
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Resumen agregado — se pinta con Alpine (loadPreview()/sync() lo traen
             en el mismo viaje): show() ya no calcula $aggregated aparte. --}}
        <template x-if="aggregated.length > 0">
            <div>
                <div class="bg-white border border-miga rounded-lg shadow-sm overflow-hidden mb-6">
                    <div class="px-5 py-3 border-b border-miga">
                        <h3 class="text-sm font-semibold text-corteza">Total a producir</h3>
                    </div>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-miga">
                            <template x-for="entry in aggregated" :key="entry.product_id">
                                <tr>
                                    <td class="px-5 py-2 text-corteza" x-text="entry.name"></td>
                                    <td class="px-5 py-2 text-right font-mono text-corteza">
                                        <span x-text="fmtQty(entry.quantity)"></span> <span x-text="entry.unit"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Preview de insumos --}}
                <x-production-consumption-preview shortfall-note="Algún insumo no alcanza: producir igual descuenta lo que hay y deja el stock en negativo." />
            </div>
        </template>

        @can('manage-costs')
            @if($editable)
                @include('production-orders.modals.request')
            @endif
        @endcan
    </div>
</x-app-layout>

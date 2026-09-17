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
            <h2 class="text-base font-semibold text-corteza mt-2">{{ $productionOrder->numberLabel() }}</h2>
            <div class="flex items-center gap-3 mt-1">
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
                                <span class="text-xs text-masa-madre">{{ $request->numberLabel() }} · {{ $request->destination_type->label() }}</span>
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

                        @include('production-orders.partials.request-lines', ['productionOrder' => $productionOrder, 'request' => $request, 'showGrid' => $showGrid])
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
            @include('production-orders.modals.save-template')
        @endcan
    </div>
</x-app-layout>

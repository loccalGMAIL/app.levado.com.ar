@php
    $errorsInRequest = $errors->hasAny(['destination_type', 'destination_id', 'notes', 'lines']);
@endphp

{{--
    Mismo modal "Nuevo pedido" que index/dashboard (destino + grilla de
    artículos con "Traer del pedido anterior", en modo deferred: las líneas
    viajan como inputs ocultos dentro de este <form>) — sin fecha ni "Se
    repite", que no aplican: la fecha ya la tiene esta orden y el pedido se
    agrega directo a ella (ProductionOrderRequestController::store), no vía
    ProductionOrderService::placeRequest() (ver request-create.blade.php).
--}}
<x-crud-modal name="production-order-request-create" title="Nuevo pedido" max-width="3xl" :show="$errorsInRequest">
    <form method="POST" action="{{ route('production-orders.requests.store', $productionOrder) }}" class="space-y-5"
        x-data="{
            ...productionOrderLines({
                deferred: true,
                products: products,
                previousUrl: @js(route('production-requests.previous-lines')),
            }),
            destination: '{{ old('destination', '') }}',
            get destinationType() { return this.destination.split(':')[0] || ''; },
            get destinationId() { return this.destination.split(':')[1] || ''; },
        }">
        @csrf

        <div class="flex flex-col md:flex-row md:items-end gap-4 pb-4 border-b border-miga">
            <div class="flex-1">
                <x-input-label for="request_destination" value="Destino" />
                @include('production-orders.partials.destination-select', ['id' => 'request_destination', 'locations' => $locations, 'customers' => $customers])
                <x-input-error :messages="$errors->get('destination_type')" class="mt-2" />
                <x-input-error :messages="$errors->get('destination_id')" class="mt-2" />
            </div>

            <div class="flex gap-3 md:pb-0.5">
                <x-primary-button data-loading="Cargando…">Agregar pedido</x-primary-button>
                <button type="button"
                    x-on:click="$dispatch('close-modal', 'production-order-request-create')"
                    class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                    Cancelar
                </button>
            </div>
        </div>

        <div>
            <x-input-label value="Artículos" />
            <div class="mt-1 border border-miga rounded-lg overflow-hidden">
                @include('production-orders.partials.lines-grid', ['pickerId' => 'request-create-in-order-picker', 'products' => $products, 'showFooter' => false])
            </div>
            <x-input-error :messages="$errors->get('lines')" class="mt-2" />

            <template x-for="(line, i) in lines" :key="line.id ?? ('new-' + i)">
                <span>
                    <input type="hidden" :name="`lines[${i}][product_id]`" :value="line.product_id">
                    <input type="hidden" :name="`lines[${i}][quantity]`" :value="line.quantity">
                </span>
            </template>
        </div>

        <div>
            <x-input-label for="request_notes" value="Notas (opcional)" />
            <textarea id="request_notes" name="notes" rows="2" maxlength="1000"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno"></textarea>
        </div>
    </form>
</x-crud-modal>

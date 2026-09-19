@php
    $errorsInRequestCreate = $errors->hasAny(['destination_type', 'destination_id', 'scheduled_for', 'notes', 'lines', 'recurrence']);
@endphp

{{--
    Alta de un pedido suelto: no pide crear una orden primero — la orden del
    día se encuentra o se crea sola (ProductionOrderService::placeRequest()).
    La grilla es la misma productionOrderLines de siempre, en modo deferred:
    sin guardado propio, las líneas viajan como inputs ocultos dentro de
    este mismo <form> y se mandan de una junto con el resto.
--}}
<x-crud-modal name="production-request-create" title="Nuevo pedido" max-width="3xl" :show="$errorsInRequestCreate">
    <form method="POST" action="{{ route('production-requests.store') }}" class="space-y-5"
        x-data="{
            ...productionOrderLines({
                deferred: true,
                products: products,
                previousUrl: @js(route('production-requests.previous-lines')),
            }),
            destinationType: '{{ old('destination_type', 'location') }}',
            destinationId: '{{ old('destination_id', '') }}',
            recurs: false,
            weekdays: [],
            endsOn: '',
        }">
        @csrf

        <div class="flex flex-col md:flex-row md:items-start gap-4">
            <div>
                <x-input-label value="Destino" />
                <div class="mt-1 flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-corteza">
                        <input type="radio" name="destination_type" value="location" x-model="destinationType"
                            @change="destinationId = ''" class="border-gray-300 text-horno focus:ring-horno">
                        Sucursal
                    </label>
                    <label class="flex items-center gap-2 text-sm text-corteza">
                        <input type="radio" name="destination_type" value="delivery_person" x-model="destinationType"
                            @change="destinationId = ''" class="border-gray-300 text-horno focus:ring-horno">
                        Repartidor
                    </label>
                </div>
                <x-input-error :messages="$errors->get('destination_type')" class="mt-2" />
            </div>

            <div class="flex-1" x-show="destinationType === 'location'">
                <x-input-label for="request_create_location" value="Sucursal" />
                <select id="request_create_location" x-model="destinationId"
                    x-bind:name="destinationType === 'location' ? 'destination_id' : ''"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Elegí una sucursal…</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1" x-show="destinationType === 'delivery_person'">
                <x-input-label for="request_create_delivery_person" value="Repartidor" />
                <select id="request_create_delivery_person" x-model="destinationId"
                    x-bind:name="destinationType === 'delivery_person' ? 'destination_id' : ''"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Elegí un repartidor…</option>
                    @foreach($deliveryPeople as $deliveryPerson)
                        <option value="{{ $deliveryPerson->id }}">{{ $deliveryPerson->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full md:w-44">
                <x-input-label for="request_create_scheduled_for" value="Fecha de entrega" />
                <input id="request_create_scheduled_for" type="date" name="scheduled_for"
                    value="{{ old('scheduled_for', now()->toDateString()) }}"
                    class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                <x-input-error :messages="$errors->get('scheduled_for')" class="mt-2" />
            </div>
        </div>
        <x-input-error :messages="$errors->get('destination_id')" class="mt-2" />

        <div>
            <x-input-label value="Artículos" />
            <div class="mt-1 border border-miga rounded-lg overflow-hidden">
                @include('production-orders.partials.lines-grid', ['pickerId' => 'request-create-picker', 'products' => $products, 'showFooter' => false])
            </div>
            <x-input-error :messages="$errors->get('lines')" class="mt-2" />

            {{-- Inputs ocultos: la grilla vive en un <select>+<table> que no
                 son <input name="lines[...]"> — este bloque es lo único que
                 realmente se manda al servidor. --}}
            <template x-for="(line, i) in lines" :key="line.id ?? ('new-' + i)">
                <span>
                    <input type="hidden" :name="`lines[${i}][product_id]`" :value="line.product_id">
                    <input type="hidden" :name="`lines[${i}][quantity]`" :value="line.quantity">
                </span>
            </template>
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-corteza">
                <input type="checkbox" x-model="recurs" class="rounded border-gray-300 text-horno focus:ring-horno">
                Se repite
            </label>

            <template x-if="recurs">
                <div class="mt-2 p-3 bg-miga rounded-lg space-y-3">
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="flex gap-2">
                            {{-- Lista, no array asociativo: "M" se repite (martes
                                 y miércoles) y pisaría la clave en un ['L'=>1,...]. --}}
                            @foreach([[1, 'L'], [2, 'M'], [3, 'M'], [4, 'J'], [5, 'V'], [6, 'S'], [7, 'D']] as [$iso, $label])
                                <label class="flex flex-col items-center gap-1 text-xs text-corteza">
                                    <input type="checkbox" value="{{ $iso }}" x-model.number="weekdays"
                                        class="rounded border-gray-300 text-horno focus:ring-horno">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap gap-3 text-xs pb-1">
                            <button type="button" @click="weekdays = [1, 2, 3, 4, 5, 6]" class="text-horno hover:underline">Lunes a sábado</button>
                            <button type="button" @click="weekdays = [1, 2, 3, 4, 5, 6, 7]" class="text-horno hover:underline">Todos los días</button>
                            <button type="button" @click="weekdays = [1, 2, 3, 4, 5]" class="text-horno hover:underline">Lunes a viernes</button>
                        </div>

                        <div>
                            <x-input-label value="Repetir hasta (opcional)" />
                            <input type="date" x-model="endsOn" class="mt-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        </div>
                    </div>
                    <p class="text-xs text-amber-700">
                        Se van generando los próximos pedidos cada vez que entrás a Órdenes — no hace falta hacer nada más.
                    </p>
                    <x-input-error :messages="$errors->get('recurrence.weekdays')" class="mt-1" />

                    <template x-for="d in weekdays" :key="d">
                        <input type="hidden" name="recurrence[weekdays][]" :value="d">
                    </template>
                    <template x-if="endsOn">
                        <input type="hidden" name="recurrence[ends_on]" :value="endsOn">
                    </template>
                </div>
            </template>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Cargando…">Cargar pedido</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'production-request-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

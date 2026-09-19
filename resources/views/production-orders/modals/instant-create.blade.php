@php
    $errorsInInstantCreate = $errors->hasAny(['destination_type', 'destination_id', 'notes', 'items']);
@endphp

{{--
    Orden instantánea: un solo paso — destino + artículos/cantidad — que crea
    la orden, la confirma y la produce en el mismo request
    (InstantProductionOrderController::store()). Misma grilla productionOrderLines
    que "Nuevo pedido" (modo deferred, sin "Traer del pedido anterior": no
    tiene sentido para un alta de urgencia). Sin preview de consumo acá — se
    ve después, en el detalle de la orden ya creada (show.blade.php), vía el
    mismo <x-production-consumption-preview>. Los campos ocultos van como
    "items[...]", no "lines[...]": StoreInstantProductionOrderRequest valida
    ese nombre.
--}}
<x-crud-modal name="production-instant-create" title="Orden instantánea" max-width="3xl" :show="$errorsInInstantCreate">
    <form method="POST" action="{{ route('production-orders.instant.store') }}" class="space-y-5"
        x-data="{
            ...productionOrderLines({ deferred: true, products: products }),
            destinationType: '{{ old('destination_type', 'location') }}',
            destinationId: '{{ old('destination_id', '') }}',
            showNotes: {{ $errors->has('notes') || old('notes') ? 'true' : 'false' }},
            get validLines() { return this.lines.filter(l => l.product_id !== '' && Number(l.quantity) > 0); },
            get canSubmit() { return this.destinationId !== '' && this.validLines.length > 0; },
        }">
        @csrf

        <!-- <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-md px-3 py-2">
            El destino es informativo, para la planilla de reparto — el stock producido entra igual al obrador, no se mueve al destino elegido.
        </p> -->

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
                <x-input-label for="instant_create_location" value="Sucursal" />
                <select id="instant_create_location" x-model="destinationId"
                    x-bind:name="destinationType === 'location' ? 'destination_id' : ''"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Elegí una sucursal…</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1" x-show="destinationType === 'delivery_person'">
                <x-input-label for="instant_create_delivery_person" value="Repartidor" />
                <select id="instant_create_delivery_person" x-model="destinationId"
                    x-bind:name="destinationType === 'delivery_person' ? 'destination_id' : ''"
                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Elegí un repartidor…</option>
                    @foreach($deliveryPeople as $deliveryPerson)
                        <option value="{{ $deliveryPerson->id }}">{{ $deliveryPerson->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <x-input-error :messages="$errors->get('destination_id')" class="mt-2" />

        <div>
            <x-input-label value="Artículos" />
            <div class="mt-1 border border-miga rounded-lg overflow-hidden">
                @include('production-orders.partials.lines-grid', ['pickerId' => 'instant-create-picker', 'products' => $products, 'showFooter' => false, 'showPrevious' => false])
            </div>
            <x-input-error :messages="$errors->get('items')" class="mt-2" />

            {{-- Inputs ocultos: "items[...]", no "lines[...]" — nombre que
                 espera StoreInstantProductionOrderRequest. --}}
            <template x-for="(line, i) in lines" :key="line.id ?? ('new-' + i)">
                <span>
                    <input type="hidden" :name="`items[${i}][product_id]`" :value="line.product_id">
                    <input type="hidden" :name="`items[${i}][quantity]`" :value="line.quantity">
                </span>
            </template>
        </div>

        <div>
            <button type="button" x-show="! showNotes" @click="showNotes = true" class="text-sm text-horno hover:underline">
                + Agregar nota
            </button>
            <template x-if="showNotes">
                <div>
                    <x-input-label for="instant_create_notes" value="Notas" />
                    <textarea id="instant_create_notes" name="notes" rows="2" maxlength="1000"
                        class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                </div>
            </template>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Produciendo…" x-bind:disabled="! canSubmit">Producir ahora</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'production-instant-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

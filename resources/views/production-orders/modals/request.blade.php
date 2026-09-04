@php
    $errorsInRequest = $errors->hasAny(['destination_type', 'destination_id', 'notes']);
@endphp

<x-crud-modal name="production-order-request-create" title="Nuevo pedido" :show="$errorsInRequest">
    <form method="POST" action="{{ route('production-orders.requests.store', $productionOrder) }}" class="space-y-4"
        x-data="{ destinationType: '{{ old('destination_type', 'location') }}' }">
        @csrf

        <div>
            <x-input-label value="Destino" />
            <div class="mt-1 flex gap-4">
                <label class="flex items-center gap-2 text-sm text-corteza">
                    <input type="radio" name="destination_type" value="location" x-model="destinationType"
                        class="border-gray-300 text-horno focus:ring-horno">
                    Sucursal
                </label>
                <label class="flex items-center gap-2 text-sm text-corteza">
                    <input type="radio" name="destination_type" value="delivery_person" x-model="destinationType"
                        class="border-gray-300 text-horno focus:ring-horno">
                    Repartidor
                </label>
            </div>
            <x-input-error :messages="$errors->get('destination_type')" class="mt-2" />
        </div>

        <div x-show="destinationType === 'location'">
            <x-input-label for="location_destination_id" value="Sucursal" />
            <select id="location_destination_id" x-bind:name="destinationType === 'location' ? 'destination_id' : ''"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                <option value="">Elegí una sucursal…</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="destinationType === 'delivery_person'">
            <x-input-label for="delivery_person_destination_id" value="Repartidor" />
            <select id="delivery_person_destination_id" x-bind:name="destinationType === 'delivery_person' ? 'destination_id' : ''"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                <option value="">Elegí un repartidor…</option>
                @foreach($deliveryPeople as $deliveryPerson)
                    <option value="{{ $deliveryPerson->id }}">{{ $deliveryPerson->name }}</option>
                @endforeach
            </select>
        </div>
        <x-input-error :messages="$errors->get('destination_id')" class="mt-2" />

        <div>
            <x-input-label for="request_notes" value="Notas (opcional)" />
            <textarea id="request_notes" name="notes" rows="2" maxlength="1000"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno"></textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Agregar pedido</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'production-order-request-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

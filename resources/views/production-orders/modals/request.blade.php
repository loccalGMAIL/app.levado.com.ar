@php
    $errorsInRequest = $errors->hasAny(['destination_type', 'destination_id', 'notes']);
@endphp

<x-crud-modal name="production-order-request-create" title="Nuevo pedido" :show="$errorsInRequest">
    <form method="POST" action="{{ route('production-orders.requests.store', $productionOrder) }}" class="space-y-4"
        x-data="{ destination: '{{ old('destination', '') }}' }">
        @csrf

        <div>
            <x-input-label for="request_destination" value="Destino" />
            @include('production-orders.partials.destination-select', ['id' => 'request_destination', 'locations' => $locations, 'customers' => $customers])
            <x-input-error :messages="$errors->get('destination_type')" class="mt-2" />
            <x-input-error :messages="$errors->get('destination_id')" class="mt-2" />
        </div>

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

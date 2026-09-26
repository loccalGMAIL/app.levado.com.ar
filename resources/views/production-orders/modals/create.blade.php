@php
    $errorsInCreate = $errors->hasAny(['type', 'scheduled_for', 'notes']);
@endphp

{{--
    Sólo Espontánea: Diaria ya no se crea a mano — nace sola cuando alguien
    carga un pedido (ProductionOrderService::orderForDate(), ver
    modals/request-create.blade.php). El tipo va fijo, sin radio.
--}}
<x-crud-modal name="production-order-create" title="Nueva orden espontánea" :show="$errorsInCreate">
    <form method="POST" action="{{ route('production-orders.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="type" value="spontaneous">

        <p class="text-sm text-masa-madre">
            Para lo que hay que fabricar ahora mismo, sin agendar. Los pedidos del día se cargan
            desde <button type="button" @click="$dispatch('close-modal', 'production-order-create'); $dispatch('open-modal', 'production-request-create')" class="text-horno hover:underline">+ Nuevo pedido</button>.
        </p>

        <div>
            <x-input-label for="notes" value="Notas (opcional)" />
            <textarea id="notes" name="notes" rows="2" maxlength="1000"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Creando…">Crear orden</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'production-order-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

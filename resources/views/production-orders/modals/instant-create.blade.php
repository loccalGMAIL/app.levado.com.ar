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
            destination: '{{ old('destination', '') }}',
            get destinationId() { return this.destination.split(':')[1] || ''; },
            showNotes: {{ $errors->has('notes') || old('notes') ? 'true' : 'false' }},
            get validLines() { return this.lines.filter(l => l.product_id !== '' && Number(l.quantity) > 0); },
            get canSubmit() { return this.destinationId !== '' && this.validLines.length > 0; },
        }">
        @csrf

        <!-- <p class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-md px-3 py-2">
            El destino es informativo, para la planilla de reparto — el stock producido entra igual al obrador, no se mueve al destino elegido.
        </p> -->

        <div>
            <x-input-label for="instant_create_destination" value="Destino" />
            @include('production-orders.partials.destination-select', ['id' => 'instant_create_destination', 'locations' => $locations, 'customers' => $customers])
            <x-input-error :messages="$errors->get('destination_type')" class="mt-2" />
            <x-input-error :messages="$errors->get('destination_id')" class="mt-2" />
        </div>

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

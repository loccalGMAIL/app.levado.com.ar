@php
    $perceptionTypes = \App\Enums\PerceptionType::cases();
    $jurisdictions = \App\Enums\Jurisdiction::cases();
    $perceptionBases = \App\Enums\PerceptionBase::cases();
@endphp

{{-- Alta de un concepto de percepción. Se guarda por fetch (sin recargar la página) para poder
     volver al modal desde el que se abrió —el de la percepción de la compra— con el concepto ya
     elegido. Al cerrarlo, sea guardando o cancelando, vuelve al modal previo si lo hubo. --}}
<x-crud-modal name="perception-concepts" title="Nueva percepción">
    <form class="space-y-4"
        x-data="perceptionConceptForm({ url: '{{ route('perception-concepts.store') }}' })"
        @submit.prevent="submit()"
        @open-perception-submodal.window="onOpen($event.detail)"
        @close-modal.window="onClosed($event.detail)">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {{-- Sin provincia (todo lo que no es IIBB) el tipo ocupa la fila entera, así Nombre y Calcular sobre siempre quedan juntos. --}}
            <div :class="requiresJurisdiction ? '' : 'sm:col-span-2'">
                <x-input-label value="Tipo" />
                <select x-model="form.type" required
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    @foreach($perceptionTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                <p x-show="errors.type" x-text="errors.type?.[0]" class="mt-1 text-sm text-red-600"></p>
            </div>
            <div x-show="requiresJurisdiction" x-cloak>
                <x-input-label value="Provincia" />
                <select x-model="form.jurisdiction"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    <option value="">— Elegí —</option>
                    @foreach($jurisdictions as $jurisdiction)
                        <option value="{{ $jurisdiction->value }}">{{ $jurisdiction->label() }}</option>
                    @endforeach
                </select>
                <p x-show="errors.jurisdiction" x-text="errors.jurisdiction?.[0]" class="mt-1 text-sm text-red-600"></p>
            </div>
            <div>
                <x-input-label value="Nombre" />
                <x-text-input type="text" class="mt-1 block w-full" x-model="form.name"
                    placeholder="Ej.: Perc. IIBB Buenos Aires" required />
                <p x-show="errors.name" x-text="errors.name?.[0]" class="mt-1 text-sm text-red-600"></p>
            </div>
            <div>
                <x-input-label value="Calcular sobre" />
                <select x-model="form.base_type"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                    @foreach($perceptionBases as $base)
                        <option value="{{ $base->value }}">{{ \Illuminate\Support\Str::ucfirst($base->shortLabel()) }}</option>
                    @endforeach
                </select>
                <p x-show="errors.base_type" x-text="errors.base_type?.[0]" class="mt-1 text-sm text-red-600"></p>
            </div>
            <div>
                <x-input-label value="Alícuota habitual %" />
                <x-text-input type="number" step="0.01" min="0" max="100"
                    class="mt-1 block w-full text-right" x-model="form.default_rate" />
                <p x-show="errors.default_rate" x-text="errors.default_rate?.[0]" class="mt-1 text-sm text-red-600"></p>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button x-bind:disabled="saving">Guardar</x-primary-button>
            <button type="button" x-on:click="$dispatch('close-modal', 'perception-concepts')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">Cancelar</button>
        </div>
    </form>
</x-crud-modal>

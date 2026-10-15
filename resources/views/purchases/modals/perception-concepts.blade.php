@php
    $perceptionTypes = \App\Enums\PerceptionType::cases();
    $jurisdictions = \App\Enums\Jurisdiction::cases();
    $allConcepts = app(\App\Models\Tenant::class)->perceptionConcepts()->orderBy('name')->get();
    $errorsInConcept = $errors->hasAny(['type', 'jurisdiction', 'name', 'default_rate']) && old('_form') === 'perception-concept';
@endphp

<x-crud-modal name="perception-concepts" title="Conceptos de percepción" max-width="2xl"
    :show="session('reopen_perception_concepts') || $errorsInConcept">
    <div class="space-y-5"
        x-data="{
            type: @js(old('type', 'iibb')),
            requiresJurisdiction(type) { return type === 'iibb'; },
        }">
        <p class="text-sm text-masa-madre">
            Armá el catálogo de impuestos que suelen venir en tus facturas (IIBB de cada provincia, percepción de IVA, etc.)
            con su alícuota habitual. Después los elegís al cargar una compra.
        </p>

        @if($allConcepts->isEmpty())
            <p class="text-sm text-masa-madre">Todavía no cargaste ningún concepto.</p>
        @else
            <div class="divide-y divide-miga border border-miga rounded-md">
                @foreach($allConcepts as $concept)
                    <div class="px-3 py-2 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-corteza truncate">
                                {{ $concept->name }}
                                @unless($concept->is_active)
                                    <span class="ml-1 text-xs text-masa-madre">(inactivo)</span>
                                @endunless
                            </p>
                            <p class="text-xs text-masa-madre">
                                {{ $concept->type->label() }}
                                @if($concept->jurisdiction) · {{ $concept->jurisdiction->label() }} @endif
                                @if($concept->default_rate !== null) · {{ rtrim(rtrim(number_format((float) $concept->default_rate, 4, ',', '.'), '0'), ',') }}% @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <form method="POST" action="{{ route('perception-concepts.update', $concept) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="type" value="{{ $concept->type->value }}">
                                <input type="hidden" name="jurisdiction" value="{{ $concept->jurisdiction?->value }}">
                                <input type="hidden" name="name" value="{{ $concept->name }}">
                                <input type="hidden" name="default_rate" value="{{ $concept->default_rate }}">
                                <input type="hidden" name="is_active" value="{{ $concept->is_active ? 0 : 1 }}">
                                <button type="submit" class="text-xs text-masa-madre hover:text-corteza underline">
                                    {{ $concept->is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('perception-concepts.destroy', $concept) }}"
                                onsubmit="return confirm('¿Eliminar este concepto? Las compras ya cargadas conservan sus datos.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('perception-concepts.store') }}" class="space-y-3 bg-miga/50 rounded-md p-4">
            @csrf
            <input type="hidden" name="_form" value="perception-concept">
            <p class="text-xs font-semibold text-corteza">Nuevo concepto</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <x-input-label value="Tipo" />
                    <select name="type" x-model="type" required
                        class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                        @foreach($perceptionTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-1" />
                </div>
                <div x-show="requiresJurisdiction(type)" x-cloak>
                    <x-input-label value="Provincia" />
                    <select name="jurisdiction" :disabled="!requiresJurisdiction(type)"
                        class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                        <option value="">— Elegí —</option>
                        @foreach($jurisdictions as $jurisdiction)
                            <option value="{{ $jurisdiction->value }}" @selected(old('jurisdiction') === $jurisdiction->value)>{{ $jurisdiction->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('jurisdiction')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Nombre" />
                    <x-text-input name="name" type="text" class="mt-1 block w-full" :value="old('name')"
                        placeholder="Ej.: Perc. IIBB Buenos Aires" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Alícuota habitual %" />
                    <x-text-input name="default_rate" type="number" step="0.01" min="0" max="100"
                        class="mt-1 block w-full text-right" :value="old('default_rate')" />
                    <x-input-error :messages="$errors->get('default_rate')" class="mt-1" />
                </div>
            </div>

            <x-primary-button data-loading="Guardando…">Agregar concepto</x-primary-button>
        </form>
    </div>
</x-crud-modal>

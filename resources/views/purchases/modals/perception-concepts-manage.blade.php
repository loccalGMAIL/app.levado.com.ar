@php
    $perceptionBases = \App\Enums\PerceptionBase::cases();
    $manageConcepts = app(\App\Models\Tenant::class)->perceptionConcepts()->orderBy('name')->get()
        ->map(fn ($c) => $c->toClientArray())->values()->all();
    $manageBaseLabels = collect($perceptionBases)->mapWithKeys(fn ($b) => [$b->value => \Illuminate\Support\Str::ucfirst($b->shortLabel())]);
@endphp

{{-- Administración del catálogo de percepciones. Todo por fetch, sin recargar, para poder volver
     al modal de la percepción con lo cargado; las percepciones ya registradas en compras guardan
     su propia copia (alícuota, base, descripción), así que cambiar o borrar un concepto no las toca. --}}
<x-crud-modal name="perception-concepts-manage" title="Administrar percepciones" max-width="xl">
    <div class="space-y-4"
        x-data="perceptionConceptManager({
            concepts: @js($manageConcepts),
            baseLabels: @js($manageBaseLabels),
            updateUrl: '{{ route('perception-concepts.update', '__ID__') }}',
            destroyUrl: '{{ route('perception-concepts.destroy', '__ID__') }}',
        })"
        @open-perception-submodal.window="onOpen($event.detail)"
        @close-modal.window="onClosed($event.detail)">

        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-masa-madre">Las compras ya cargadas conservan sus datos al editar o eliminar.</p>
            <button type="button" @click="openNew()"
                class="text-xs text-masa-madre hover:text-corteza hover:underline shrink-0">
                + Nueva percepción
            </button>
        </div>

        <p x-show="Object.keys(errors).length" x-cloak x-text="Object.values(errors).flat()[0]"
            class="text-sm text-red-600"></p>

        <p x-show="items.length === 0" x-cloak class="text-sm text-masa-madre">Todavía no cargaste ninguna percepción.</p>

        <div x-show="items.length" x-cloak class="divide-y divide-miga border border-miga rounded-md">
            <template x-for="concept in items" :key="concept.id">
                <div class="px-3 py-2.5 text-sm">
                    <template x-if="editing?.id !== concept.id">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-medium text-corteza truncate">
                                    <span x-text="concept.name"></span>
                                    <span x-show="!concept.is_active" class="ml-1 text-xs font-normal text-masa-madre">(inactiva)</span>
                                </p>
                                <p class="text-xs text-masa-madre" x-text="summary(concept)"></p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0 text-xs">
                                <button type="button" @click="startEdit(concept)"
                                    class="text-masa-madre hover:text-corteza underline">Editar</button>
                                <button type="button" @click="toggleActive(concept)"
                                    class="text-masa-madre hover:text-corteza underline"
                                    x-text="concept.is_active ? 'Desactivar' : 'Activar'"></button>
                                <button type="button" @click="remove(concept)"
                                    class="text-red-600 hover:underline">Eliminar</button>
                            </div>
                        </div>
                    </template>

                    <template x-if="editing?.id === concept.id">
                        <form class="grid grid-cols-1 sm:grid-cols-3 gap-2" @submit.prevent="saveEdit(concept)">
                            <div class="sm:col-span-3">
                                <x-input-label value="Nombre" />
                                <x-text-input type="text" class="mt-1 block w-full" x-model="editing.name" required />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label value="Calcular sobre" />
                                <select x-model="editing.base_type"
                                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                    @foreach($perceptionBases as $base)
                                        <option value="{{ $base->value }}">{{ \Illuminate\Support\Str::ucfirst($base->shortLabel()) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Alícuota %" />
                                <x-text-input type="number" step="0.01" min="0" max="100"
                                    class="mt-1 block w-full text-right" x-model="editing.default_rate" />
                            </div>
                            <div class="sm:col-span-3 flex items-center gap-3 pt-1">
                                <x-primary-button x-bind:disabled="saving">Guardar</x-primary-button>
                                <button type="button" @click="cancelEdit()"
                                    class="px-3 py-2 text-sm text-masa-madre hover:text-corteza">Cancelar</button>
                            </div>
                        </form>
                    </template>
                </div>
            </template>
        </div>

        <div class="flex pt-1">
            <button type="button" x-on:click="$dispatch('close-modal', 'perception-concepts-manage')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">Cerrar</button>
        </div>
    </div>
</x-crud-modal>

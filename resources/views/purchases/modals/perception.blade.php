@php
    $perceptionBases = \App\Enums\PerceptionBase::cases();
    $baseAmounts = collect($perceptionBases)->mapWithKeys(fn ($b) => [$b->value => round($purchase->baseFor($b), 2)]);
    $baseLabels = collect($perceptionBases)->mapWithKeys(fn ($b) => [$b->value => \Illuminate\Support\Str::ucfirst($b->shortLabel())]);
@endphp
<x-crud-modal name="perception-form" title="Percepción" max-width="xl" :show="$errorsInPerception">
    <form method="POST"
        x-data="perceptionForm({ concepts: @js($conceptsById), bases: @js($baseAmounts), baseLabels: @js($baseLabels) })"
        @perception-concept-created.window="addConcept($event.detail)"
        @perception-concept-updated.window="updateConcept($event.detail)"
        @perception-concept-deleted.window="removeConcept($event.detail.id)"
        :action="isEditing
            ? '{{ route('purchases.perceptions.update', [$purchase, '__ID__']) }}'.replace('__ID__', form.id)
            : '{{ route('purchases.perceptions.store', $purchase) }}'"
        class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="perception">
        <input type="hidden" name="perception_id" :value="form.id">
        <template x-if="isEditing"><input type="hidden" name="_method" value="PATCH"></template>
        <input type="hidden" name="type" :value="form.type">
        <input type="hidden" name="jurisdiction" :value="form.jurisdiction">

        <div>
            <div class="flex items-center justify-between">
                <x-input-label value="Concepto del catálogo" />
                <button type="button"
                    @click="openSubmodal('perception-concepts-manage')"
                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                    Administrar
                </button>
            </div>
            <select name="perception_concept_id" x-ref="conceptSelect" x-model="form.perception_concept_id" @change="pickConcept()"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                <option value="">— Libre (sin concepto) —</option>
                @foreach($perceptionConcepts as $concept)
                    <option value="{{ $concept->id }}">{{ $concept->name }}@if($concept->default_rate !== null) — {{ rtrim(rtrim(number_format((float) $concept->default_rate, 4, ',', '.'), '0'), ',') }}%@endif · {{ \Illuminate\Support\Str::ucfirst($concept->base_type->shortLabel()) }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('perception_concept_id')" class="mt-1" />
        </div>

        {{-- Con concepto la descripción es la del catálogo (se envía igual); sin concepto (libre) se carga a mano. --}}
        <template x-if="hasConcept">
            <input type="hidden" name="description" :value="form.description">
        </template>
        <template x-if="!hasConcept">
            <div>
                <x-input-label value="Descripción" />
                <x-text-input name="description" type="text" class="mt-1 block w-full"
                    x-model="form.description" placeholder="Ej.: Perc. IIBB Buenos Aires" />
                <x-input-error :messages="$errors->get('description')" class="mt-1" />
            </div>
        </template>

        <div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <x-input-label value="Calcular sobre" />
                    {{-- Un select deshabilitado no se envía: con concepto la base viaja en un hidden. --}}
                    <template x-if="hasConcept"><input type="hidden" name="base_type" :value="form.base_type"></template>
                    <select :name="hasConcept ? null : 'base_type'" x-model="form.base_type" :disabled="hasConcept"
                        class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm disabled:bg-gray-100 disabled:text-gray-500">
                        @foreach($perceptionBases as $base)
                            <option value="{{ $base->value }}">{{ \Illuminate\Support\Str::ucfirst($base->shortLabel()) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('base_type')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Alícuota %" />
                    <x-text-input name="rate" type="number" step="0.01" min="0" max="100"
                        class="mt-1 block w-full text-right"
                        x-model="form.rate" />
                    <x-input-error :messages="$errors->get('rate')" class="mt-1" />
                </div>
                <div>
                    <x-input-label value="Importe exacto" />
                    <x-text-input name="amount" type="number" step="0.01" min="0"
                        class="mt-1 block w-full text-right"
                        x-model="form.amount"
                        x-bind:placeholder="fmt(computedAmount)" />
                    <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                </div>
            </div>
            <p class="mt-1 text-xs text-masa-madre">
                <span x-show="hasConcept">La base la define el concepto. </span>Se calcula sobre
                <span class="font-mono" x-text="'$ ' + fmt(autoBase)"></span>.
            </p>
        </div>

        <div class="bg-miga/50 rounded-md p-3 flex items-center justify-between text-sm">
            <span class="font-medium text-corteza">Importe a registrar</span>
            <span class="font-mono font-medium text-corteza"
                x-text="'$ ' + fmt(form.amount !== '' && form.amount !== null ? form.amount : computedAmount)"></span>
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Guardar</x-primary-button>
            <button type="button" x-on:click="$dispatch('close-modal', 'perception-form')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">Cancelar</button>
        </div>
    </form>
</x-crud-modal>

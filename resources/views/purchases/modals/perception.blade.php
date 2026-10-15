<x-crud-modal name="perception-form" title="Percepción o impuesto" :show="$errorsInPerception">
    <form method="POST"
        x-data="perceptionForm({ concepts: @js($conceptsById), taxedNet: {{ $purchase->taxedNet() }} })"
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
            <x-input-label value="Concepto del catálogo" />
            <select name="perception_concept_id" x-model="form.perception_concept_id" @change="pickConcept()"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                <option value="">— Libre (sin concepto) —</option>
                @foreach($perceptionConcepts as $concept)
                    <option value="{{ $concept->id }}">{{ $concept->name }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-masa-madre">
                ¿Falta uno?
                <button type="button" class="underline hover:text-corteza"
                    @click="$dispatch('close-modal', 'perception-form'); $dispatch('open-modal', 'perception-concepts')">
                    Administrar conceptos
                </button>
            </p>
            <x-input-error :messages="$errors->get('perception_concept_id')" class="mt-1" />
        </div>

        <div>
            <x-input-label value="Descripción" />
            <x-text-input name="description" type="text" class="mt-1 block w-full"
                x-model="form.description" placeholder="Ej.: Perc. IIBB Buenos Aires" />
            <x-input-error :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label value="Base imponible" />
                <x-text-input name="base_amount" type="number" step="0.01" min="0"
                    class="mt-1 block w-full text-right"
                    x-model="form.base_amount"
                    x-bind:placeholder="'Neto gravado: ' + fmt(taxedNet)" />
                <p class="mt-1 text-xs text-masa-madre">Vacía = neto gravado de la compra.</p>
                <x-input-error :messages="$errors->get('base_amount')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Alícuota %" />
                <x-text-input name="rate" type="number" step="0.01" min="0" max="100"
                    class="mt-1 block w-full text-right"
                    x-model="form.rate" />
                <x-input-error :messages="$errors->get('rate')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label value="Importe exacto de la factura (opcional)" />
            <x-text-input name="amount" type="number" step="0.01" min="0"
                class="mt-1 block w-full text-right"
                x-model="form.amount"
                x-bind:placeholder="'Calculado: ' + fmt(computedAmount)" />
            <p class="mt-1 text-xs text-masa-madre">
                Si lo completás se respeta tal cual (cierra contra el papel) y no cambia al editar renglones.
                Vacío = base × alícuota.
            </p>
            <x-input-error :messages="$errors->get('amount')" class="mt-1" />
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

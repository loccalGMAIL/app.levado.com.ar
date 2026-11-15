{{-- Alta y edición de combo en un mismo modal: comboForm.id decide a dónde se envía. --}}
<x-crud-modal name="product-combo" title="Combo" max-width="2xl" :show="$errorsInComboForm" :focusable="false">
    <form method="POST"
        :action="comboForm.id ? `{{ url('products/combos') }}/${comboForm.id}` : '{{ route('products.combos.store') }}'"
        class="space-y-4">
        @csrf
        <template x-if="comboForm.id">
            <input type="hidden" name="_method" value="PUT">
        </template>
        <input type="hidden" name="_form" value="combo">
        <input type="hidden" name="combo_id" x-bind:value="comboForm.id">

        <div>
            <x-input-label for="combo_name" value="Nombre" />
            <x-text-input id="combo_name" name="name" type="text" class="mt-1 block w-full"
                x-model="comboForm.name" placeholder="Ej. Desayuno: café + 2 medialunas" required />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="combo_component_picker" value="Artículos del combo" />
            <select id="combo_component_picker" data-searchable @change="addComboLine($event.target)"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                <option value="">— Buscá un artículo para agregarlo —</option>
                @foreach($components as $component)
                    <option value="{{ $component->id }}">{{ $component->name }} ({{ $component->type->label() }})</option>
                @endforeach
            </select>

            <div class="mt-3 border border-miga rounded-md divide-y divide-miga" x-show="comboForm.lines.length > 0" x-cloak>
                <template x-for="(line, index) in comboForm.lines" :key="line.product_id">
                    <div class="flex items-center gap-3 px-3 py-2">
                        <input type="hidden" :name="`lines[${index}][product_id]`" :value="line.product_id">
                        <span class="flex-1 min-w-0 text-sm text-corteza truncate" x-text="line.name"></span>
                        <input type="number" step="0.001" min="0.001" required
                            :name="`lines[${index}][quantity]`" x-model="line.quantity"
                            :aria-label="`Cantidad de ${line.name}`"
                            class="w-24 text-right text-sm border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                        <span class="w-8 text-xs text-masa-madre" x-text="line.unit"></span>
                        <button type="button" @click="comboForm.lines.splice(index, 1)"
                            :aria-label="`Quitar ${line.name}`" title="Quitar"
                            class="text-masa-madre hover:text-red-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </template>
                <div class="flex items-center justify-between px-3 py-2 text-sm bg-miga/40">
                    <span class="text-masa-madre">Costo del combo</span>
                    <span class="font-mono text-corteza" x-text="comboCostFormatted"></span>
                </div>
            </div>
            <p class="mt-2 text-xs text-masa-madre" x-show="comboForm.lines.length === 0">
                Sumá dos o más artículos, elaborados o de reventa. Al venderse el combo, el stock baja en cada uno.
            </p>
            <x-input-error :messages="collect($errors->get('lines'))->merge($errors->get('lines.*'))->flatten()->unique()->all()" class="mt-2" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="combo_category" value="Categoría (opcional)" />
                <select id="combo_category" name="product_category_id" x-model="comboForm.product_category_id"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Sin categoría —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('product_category_id')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="combo_barcode" value="Código de barras (opcional)" />
                <x-text-input id="combo_barcode" name="barcode" type="text" class="mt-1 block w-full"
                    x-model="comboForm.barcode" placeholder="Se genera solo si lo dejás vacío" />
                <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
            </div>
        </div>

        <p class="text-xs text-masa-madre">El precio se carga en la lista una vez guardado, como en cualquier artículo.</p>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">
                <span x-text="comboForm.id ? 'Guardar cambios' : 'Crear combo'"></span>
            </x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'product-combo')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

@php
    // Lista, no array asociativo: "M" se repite (martes y miércoles) y
    // pisaría la clave en un ['L'=>1,...] — mismo motivo que en el modal de alta.
    $weekdayOptions = [[1, 'L'], [2, 'M'], [3, 'M'], [4, 'J'], [5, 'V'], [6, 'S'], [7, 'D']];
@endphp

{{--
    Editar un pedido recurrente ya existente: días, vigencia, notas y
    artículos (NO el destino — ver recurring.blade.php). Un solo modal
    compartido por todas las filas: <template x-if="editing"> desmonta y
    remonta el x-data de la grilla con los datos de cada fila (el botón
    "Editar" pone editing en null antes de asignar el registro nuevo, así
    x-if siempre pasa por false→true y nunca reusa las líneas del anterior).
--}}
<x-crud-modal name="production-recurring-edit" title="Editar pedido recurrente" max-width="2xl">
    <template x-if="editing">
        <form method="POST" :action="editing.updateUrl" class="space-y-5"
            x-data="{
                ...productionOrderLines({ deferred: true, products: products, lines: editing.lines }),
                weekdays: editing.weekdays,
                endsOn: editing.ends_on,
                notes: editing.notes,
            }">
            @csrf
            @method('PATCH')

            <div>
                <x-input-label value="Días" />
                <div class="mt-1 flex gap-2">
                    @foreach($weekdayOptions as [$iso, $label])
                        <label class="flex flex-col items-center gap-1 text-xs text-corteza">
                            <input type="checkbox" value="{{ $iso }}" name="weekdays[]" x-model.number="weekdays"
                                class="rounded border-gray-300 text-horno focus:ring-horno">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <x-input-label for="recurring_edit_ends_on" value="Repetir hasta (opcional)" />
                <input id="recurring_edit_ends_on" type="date" name="ends_on" x-model="endsOn"
                    class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
            </div>

            <div>
                <x-input-label value="Artículos" />
                <div class="mt-1 border border-miga rounded-lg overflow-hidden">
                    @include('production-orders.partials.lines-grid', ['pickerId' => 'recurring-edit-picker', 'products' => $products, 'showFooter' => false])
                </div>
                <template x-for="(line, i) in lines" :key="line.id ?? ('new-' + i)">
                    <span>
                        <input type="hidden" :name="`lines[${i}][product_id]`" :value="line.product_id">
                        <input type="hidden" :name="`lines[${i}][quantity]`" :value="line.quantity">
                    </span>
                </template>
            </div>

            <div>
                <x-input-label for="recurring_edit_notes" value="Notas (opcional)" />
                <textarea id="recurring_edit_notes" name="notes" rows="2" maxlength="1000" x-model="notes"
                    class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno"></textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <x-primary-button data-loading="Guardando…">Guardar</x-primary-button>
                <button type="button"
                    x-on:click="$dispatch('close-modal', 'production-recurring-edit')"
                    class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                    Cancelar
                </button>
            </div>
        </form>
    </template>
</x-crud-modal>

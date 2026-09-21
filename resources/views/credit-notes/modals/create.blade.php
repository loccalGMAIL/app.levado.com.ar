@php
    $errorsInCreditNoteCreate = $errors->hasAny(['supplier_id', 'purchase_id', 'note_number', 'note_date', 'notes']) && old('_form') === 'credit-note-create';
@endphp

{{--
    $openOnLoad: sólo lo manda credit-notes/index.blade.php cuando se llega con
    ?purchase_id= (acción «Nota de crédito» de un renglón de /purchases). El
    include de purchases/show.blade.php no lo pasa, así que el modal ahí nunca
    se abre solo aunque también reciba $purchase.
--}}
<x-crud-modal name="credit-note-create" title="Nueva nota de crédito" :show="$errorsInCreditNoteCreate || ($openOnLoad ?? false)">
    <form method="POST" action="{{ route('credit-notes.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="_form" value="credit-note-create">

        @if(isset($purchase) && $purchase)
            <input type="hidden" name="supplier_id" value="{{ $purchase->supplier_id }}">
            <input type="hidden" name="purchase_id" value="{{ $purchase->id }}">
            <div class="bg-miga/50 rounded-md p-3 text-sm">
                <div class="font-medium text-corteza">{{ $purchase->supplier->name }}</div>
                <div class="text-xs text-masa-madre mt-0.5">
                    {{ $purchase->invoice_number ? 'Factura N° '.$purchase->invoice_number : 'Compra sin número de factura' }}
                    · {{ $purchase->invoice_date->format('d/m/Y') }}
                </div>
            </div>
        @else
            <div>
                <x-input-label for="create_cn_supplier" value="Proveedor" />
                <select id="create_cn_supplier" name="supplier_id" required
                    data-searchable
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Seleccioná un proveedor —</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="create_cn_purchase" value="Compra de origen" />
                <select id="create_cn_purchase" name="purchase_id"
                    data-searchable
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
                    <option value="">— Sin compra asociada (reconocimiento económico) —</option>
                    @foreach($purchases as $p)
                        <option value="{{ $p->id }}" @selected(old('purchase_id') == $p->id)>
                            {{ $p->supplier->name }} · {{ $p->invoice_number ?? 'sin número' }} · {{ $p->invoice_date->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-masa-madre">
                    Sin compra de origen, ningún renglón de esta nota puede descontar stock: sólo sirve para un
                    reconocimiento económico puro.
                </p>
                <x-input-error :messages="$errors->get('purchase_id')" class="mt-2" />
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-input-label for="create_cn_date" value="Fecha de la nota" />
                <x-text-input id="create_cn_date" name="note_date" type="date"
                    class="mt-1 block w-full"
                    :value="old('note_date', date('Y-m-d'))"
                    required />
                <x-input-error :messages="$errors->get('note_date')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="create_cn_number" value="N° de nota" />
                <x-text-input id="create_cn_number" name="note_number" type="text"
                    class="mt-1 block w-full"
                    :value="old('note_number')"
                    placeholder="Opcional" />
                <x-input-error :messages="$errors->get('note_number')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="create_cn_notes" value="Notas" />
            <textarea id="create_cn_notes" name="notes"
                rows="2"
                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm"
                placeholder="Opcional">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex gap-3 pt-2">
            <x-primary-button data-loading="Guardando…">Registrar nota de crédito</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'credit-note-create')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

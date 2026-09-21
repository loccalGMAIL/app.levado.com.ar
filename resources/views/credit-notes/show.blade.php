<x-app-layout>
    <x-slot name="title">
        {{ $creditNote->note_number ? 'NC #'.$creditNote->note_number : 'Nota de crédito #'.$creditNote->id }}
    </x-slot>

    @php
        $errorsInEditCreditNote = $errors->hasAny(['supplier_id', 'purchase_id', 'note_number', 'note_date', 'notes']) && old('_form') === 'credit-note-edit';
        $errorsInAddLine = $errors->hasAny(['purchase_line_id', 'description', 'quantity', 'unit', 'unit_price']) && old('_form') === 'credit-note-add-line';

        $totalSubtotal = $creditNote->lines->sum(fn ($l) => (float) $l->subtotal);
        $totalIva = $creditNote->lines->sum(fn ($l) => (float) $l->subtotal * (float) $l->iva_rate);
        $grandTotal = $totalSubtotal + $totalIva;
    @endphp

    <div class="py-8 px-6 lg:px-8" x-data="{}">

        <div class="pb-0">
            <a href="{{ route('credit-notes.index') }}"
                class="inline-flex items-center gap-1.5 text-sm text-masa-madre hover:text-corteza transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Volver a notas de crédito
            </a>
        </div>

        <div class="space-y-6 mt-4">

            {{-- Header --}}
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div class="min-w-0 space-y-1">
                        <h2 class="text-base font-semibold text-corteza">
                            {{ $creditNote->note_number ? 'Nota de crédito N° '.$creditNote->note_number : 'Nota de crédito sin número' }}
                        </h2>
                        <p class="text-sm text-masa-madre">{{ $creditNote->supplier->name }} · {{ $creditNote->note_date->format('d/m/Y') }}</p>
                        @if($creditNote->purchase)
                            <p class="text-xs text-masa-madre">
                                Origen:
                                <a href="{{ route('purchases.show', $creditNote->purchase) }}" class="hover:underline text-corteza">
                                    {{ $creditNote->purchase->invoice_number ? 'Factura #'.$creditNote->purchase->invoice_number : 'Compra #'.$creditNote->purchase->id }}
                                </a>
                            </p>
                        @else
                            <p class="text-xs text-masa-madre">Sin compra de origen — reconocimiento económico.</p>
                        @endif
                        @if($creditNote->notes)
                            <p class="text-sm text-masa-madre mt-2">{{ $creditNote->notes }}</p>
                        @endif
                    </div>
                    @can('manage-costs')
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button"
                                @click="$dispatch('open-modal', 'credit-note-edit')"
                                class="px-3 py-1.5 border border-gray-300 text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                                Editar
                            </button>
                            <form method="POST" action="{{ route('credit-notes.destroy', $creditNote) }}"
                                onsubmit="return confirm('¿Eliminar esta nota de crédito y revertir el stock de sus renglones?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="px-3 py-1.5 border border-red-300 text-red-600 text-sm rounded-md hover:bg-red-50 transition-colors">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>
            </div>

            {{-- Renglones --}}
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="flex items-center justify-between gap-3 p-6 pb-0">
                    <h3 class="text-sm font-semibold text-corteza">Renglones</h3>
                    @can('manage-costs')
                        <button type="button"
                            @click="$dispatch('open-modal', 'credit-note-add-line')"
                            class="px-3 py-1.5 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                            + Agregar renglón
                        </button>
                    @endcan
                </div>

                @if($creditNote->lines->isEmpty())
                    <p class="text-sm text-masa-madre p-6">Todavía no hay renglones en esta nota.</p>
                @else
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-miga text-masa-madre border-b border-miga">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Descripción</th>
                                    <th class="px-4 py-3 font-medium text-right">Cantidad</th>
                                    <th class="px-4 py-3 font-medium">Unidad</th>
                                    <th class="px-4 py-3 font-medium text-right">Precio unit.</th>
                                    <th class="px-4 py-3 font-medium text-right">Subtotal</th>
                                    <th class="px-4 py-3 font-medium">Stock</th>
                                    @can('manage-costs')
                                        <th class="px-4 py-3"></th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                @foreach($creditNote->lines as $line)
                                    <tr>
                                        <td class="px-4 py-3 text-corteza">
                                            {{ $line->description ?? $line->purchaseLine?->raw_name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-masa-madre">
                                            {{ number_format($line->quantity, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-masa-madre">{{ $line->unit->short() }}</td>
                                        <td class="px-4 py-3 text-right font-mono text-masa-madre">
                                            $ {{ number_format($line->unit_price, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-mono text-corteza">
                                            $ {{ number_format($line->subtotal, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($line->affectsStock())
                                                <span class="inline-flex items-center gap-1 text-green-700 text-xs font-medium"
                                                    title="{{ $line->isStockApplied() ? 'Stock descontado' : 'Pendiente de aplicar' }}">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                    </svg>
                                                    Stock descontado
                                                </span>
                                            @else
                                                <span class="text-masa-madre/60 text-xs">Sólo económico</span>
                                            @endif
                                        </td>
                                        @can('manage-costs')
                                            <td class="px-4 py-3">
                                                <form method="POST"
                                                    action="{{ route('credit-notes.lines.destroy', [$creditNote, $line]) }}"
                                                    onsubmit="return confirm('¿Eliminar este renglón?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="p-1 text-red-400 hover:text-red-600 transition-colors"
                                                        title="Eliminar">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-miga bg-miga/30">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-xs font-semibold text-corteza text-right">Totales</td>
                                    <td class="px-4 py-3"></td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-corteza text-xs whitespace-nowrap">
                                        $ {{ number_format($totalSubtotal, 2, ',', '.') }}
                                    </td>
                                    <td @can('manage-costs') colspan="2" @endcan class="px-4 py-3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

        </div>

        @can('manage-costs')
            @include('credit-notes.modals.edit')
            @include('credit-notes.modals.add-line')
        @endcan

    </div>
</x-app-layout>

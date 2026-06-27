<x-app-layout>
    <x-slot name="title">
        {{ $purchase->invoice_number ? 'Factura #' . $purchase->invoice_number : 'Compra #' . $purchase->id }}
    </x-slot>

    @php
        $errorsInAddLine      = $errors->hasAny(['raw_name', 'quantity_purchased', 'purchase_unit', 'unit_price']) && old('_form') === 'add-line';
        $errorsInEditLine     = $errors->hasAny(['quantity_purchased', 'purchase_unit', 'unit_price']) && old('_form') === 'edit-line';
        $errorsInEditPurchase = $errors->hasAny(['supplier_id', 'invoice_number', 'invoice_date', 'notes', 'default_iva_rate', 'default_percepcion_rate']) && old('_form') === 'edit-purchase';

        $editingLine = null;
        if ($errorsInEditLine) {
            $editingLine = [
                'id'                 => old('line_id'),
                'raw_name'           => old('raw_name'),
                'quantity_purchased' => old('quantity_purchased'),
                'purchase_unit'      => old('purchase_unit'),
                'unit_price'         => old('unit_price'),
                'iva_rate'           => old('iva_rate'),
                'percepcion_rate'    => old('percepcion_rate'),
            ];
        }

        $appliedCount = $purchase->lines->filter(fn ($l) => $l->isApplied())->count();
        $totalLines   = $purchase->lines->count();
        $allApplied   = $totalLines > 0 && $appliedCount === $totalLines;

        $totalSubtotal   = $purchase->lines->sum(fn ($l) => (float) $l->subtotal);
        $totalIva        = $purchase->lines->sum(fn ($l) => (float) $l->subtotal * (float) $l->iva_rate);
        $totalPercepcion = $purchase->lines->sum(fn ($l) => (float) $l->subtotal * ((float) ($l->percepcion_rate ?? 0) / 100));
        $grandTotal      = $totalSubtotal + $totalIva + $totalPercepcion;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editingLine: {{ Js::from($editingLine) }},
            openEdit(line) {
                this.editingLine = line;
                $dispatch('open-modal', 'line-edit');
            }
        }">

        {{-- Botón volver (solo móvil) --}}
        <div class="sm:hidden px-6 pt-4 pb-0">
            <a href="{{ route('purchases.index') }}"
                class="inline-flex items-center gap-1.5 text-sm text-masa-madre hover:text-corteza transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Volver a compras
            </a>
        </div>

        <div class="space-y-6">

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Header de la compra --}}
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-corteza">
                                {{ $purchase->invoice_number ? 'Factura N° ' . $purchase->invoice_number : 'Compra sin número de factura' }}
                            </h2>
                            @can('manage-costs')
                                <button type="button"
                                    @click="$dispatch('open-modal', 'purchase-edit')"
                                    class="p-1 text-masa-madre hover:text-corteza transition-colors"
                                    title="Editar compra">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 1 1 3.182 3.182L7.5 19.213l-4 1 1-4 12.362-12.726z" />
                                    </svg>
                                </button>
                            @endcan
                        </div>
                        <p class="text-sm text-masa-madre">
                            <span class="font-medium text-corteza">{{ $purchase->supplier->name }}</span>
                            &mdash; {{ $purchase->invoice_date->format('d/m/Y') }}
                        </p>
                        @if($purchase->notes)
                            <p class="text-sm text-masa-madre italic">{{ $purchase->notes }}</p>
                        @endif
                        @if($purchase->invoice_image_path)
                            <button type="button"
                                @click="$dispatch('open-modal', 'invoice-image')"
                                class="inline-flex items-center gap-1.5 text-sm text-corteza hover:text-horno transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                Ver factura original
                            </button>
                        @endif
                    </div>
                    <div class="text-right shrink-0 space-y-2">
                        <div>
                            <p class="text-xs text-masa-madre">Total renglones (sin IVA)</p>
                            <p class="text-xl font-mono font-semibold text-corteza">
                                ${{ number_format($purchase->totalAmount(), 2, ',', '.') }}
                            </p>
                        </div>
                        @if($purchase->invoice_total)
                            <div>
                                <p class="text-xs text-masa-madre">Total factura (con IVA)</p>
                                <p class="text-sm font-mono text-corteza">
                                    ${{ number_format($purchase->invoice_total, 2, ',', '.') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Renglones digitalizados (factura tal cual) --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-corteza">Renglones de la factura</h3>
                    @can('manage-costs')
                        <button type="button"
                            @click="$dispatch('open-modal', 'add-line')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-corteza border border-corteza/30 rounded-md hover:bg-miga transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Agregar renglón
                        </button>
                    @endcan
                </div>

                {{-- Banner de vinculación --}}
                @if($totalLines > 0)
                    @if($allApplied)
                        <div class="mb-3 flex items-center gap-2 px-4 py-2.5 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Todos los renglones tienen costo imputado
                        </div>
                    @else
                        <div class="mb-3 flex items-center justify-between gap-4 px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                                {{ $appliedCount }} de {{ $totalLines }} {{ $totalLines === 1 ? 'renglón tiene' : 'renglones tienen' }} costo imputado
                            </div>
                            @can('manage-costs')
                                <a href="{{ route('purchases.match', $purchase) }}"
                                    class="shrink-0 font-medium hover:underline">
                                    Vincular renglones →
                                </a>
                            @endcan
                        </div>
                    @endif
                @endif

                @if($purchase->lines->isEmpty())
                    <div class="bg-white rounded-lg shadow p-6 text-center text-masa-madre text-sm">
                        No hay renglones. Agregá uno con el botón de arriba.
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow overflow-x-auto">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-miga text-masa-madre border-b border-miga">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Descripción</th>
                                    <th class="px-4 py-3 font-medium text-right">Cantidad</th>
                                    <th class="px-4 py-3 font-medium">Unidad</th>
                                    <th class="px-4 py-3 font-medium text-right">Precio unitario</th>
                                    <th class="px-4 py-3 font-medium text-right">Subtotal</th>
                                    <th class="px-4 py-3 font-medium text-right">IVA $</th>
                                    <th class="px-4 py-3 font-medium text-right">Percepción $</th>
                                    <th class="px-4 py-3 font-medium text-right">Total</th>
                                    <th class="px-4 py-3 font-medium text-center">Costo</th>
                                    @can('manage-costs')
                                        <th class="px-4 py-3"></th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                @foreach($purchase->lines as $line)
                                    @php
                                        $ivaAmount        = (float) $line->subtotal * (float) $line->iva_rate;
                                        $percepcionAmount = (float) $line->subtotal * ((float) ($line->percepcion_rate ?? 0) / 100);
                                        $lineTotal        = (float) $line->subtotal + $ivaAmount + $percepcionAmount;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 align-top text-corteza min-w-[12rem]">
                                            {{ $line->raw_name ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-right font-mono text-corteza">
                                            {{ rtrim(rtrim(number_format($line->quantity_purchased, 4, ',', '.'), '0'), ',') }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-masa-madre">{{ $line->purchase_unit->short() }}</td>
                                        <td class="px-4 py-3 align-top text-right font-mono text-corteza">
                                            ${{ number_format($line->unit_price, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-right font-mono font-semibold text-corteza">
                                            ${{ number_format($line->subtotal, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-right font-mono text-masa-madre">
                                            ${{ number_format($ivaAmount, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-right font-mono text-masa-madre">
                                            {{ $percepcionAmount > 0 ? '$ ' . number_format($percepcionAmount, 2, ',', '.') : '—' }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-right font-mono font-semibold text-corteza">
                                            ${{ number_format($lineTotal, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 align-top text-center">
                                            @if($line->isApplied())
                                                <span class="inline-flex items-center gap-1 text-green-700 text-xs font-medium"
                                                    title="Costo imputado el {{ $line->cost_applied_at->format('d/m/Y') }}">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                    </svg>
                                                    Aplicado
                                                </span>
                                            @elseif($line->isMatched())
                                                <span class="inline-flex items-center gap-1 text-amber-600 text-xs font-medium"
                                                    title="Vinculado, pendiente de imputar">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                                                    </svg>
                                                    Pendiente
                                                </span>
                                            @else
                                                <span class="text-masa-madre/60 text-xs">Sin vincular</span>
                                            @endif
                                        </td>
                                        @can('manage-costs')
                                            <td class="px-4 py-3 align-top">
                                                <div class="flex items-center justify-end gap-3">
                                                    <button type="button"
                                                        @click="openEdit({{ Js::from([
                                                            'id' => $line->id,
                                                            'raw_name' => $line->raw_name,
                                                            'quantity_purchased' => $line->quantity_purchased,
                                                            'purchase_unit' => $line->purchase_unit->value,
                                                            'unit_price' => $line->unit_price,
                                                            'iva_rate' => $line->iva_rate,
                                                            'percepcion_rate' => $line->percepcion_rate,
                                                        ]) }})"
                                                        class="p-1 text-masa-madre hover:text-corteza transition-colors"
                                                        title="Editar">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 1 1 3.182 3.182L7.5 19.213l-4 1 1-4 12.362-12.726z" />
                                                        </svg>
                                                    </button>
                                                    <form method="POST"
                                                        action="{{ route('purchases.lines.destroy', [$purchase, $line]) }}"
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
                                                </div>
                                            </td>
                                        @endcan
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-miga bg-miga/30">
                                <tr>
                                    <td colspan="4" class="px-4 py-3 text-xs font-semibold text-corteza text-right">Totales</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-corteza text-xs">
                                        ${{ number_format($totalSubtotal, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-masa-madre text-xs">
                                        ${{ number_format($totalIva, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-masa-madre text-xs">
                                        {{ $totalPercepcion > 0 ? '$ ' . number_format($totalPercepcion, 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-corteza text-xs">
                                        ${{ number_format($grandTotal, 2, ',', '.') }}
                                    </td>
                                    <td @can('manage-costs') colspan="2" @endcan class="px-4 py-3"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-masa-madre">
                        Esta es la factura tal como se leyó. La asociación con insumos y la actualización de costos se hacen en un paso aparte.
                    </p>
                @endif
            </div>

            {{-- Acción eliminar compra --}}
            @can('manage-costs')
                @if($purchase->lines->isEmpty())
                    <div>
                        <form method="POST" action="{{ route('purchases.destroy', $purchase) }}"
                            onsubmit="return confirm('¿Eliminar esta compra?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="text-xs text-red-400 hover:text-red-600 hover:underline">
                                Eliminar esta compra
                            </button>
                        </form>
                    </div>
                @endif
            @endcan

        </div>

        @can('manage-costs')
            @include('purchases.modals.edit-line')
            @include('purchases.modals.edit-purchase')
            @include('purchases.modals.add-line')
        @endcan

        {{-- Modal: factura original --}}
        @if($purchase->invoice_image_path)
            <x-crud-modal name="invoice-image" title="Factura original" max-width="2xl">
                <div class="space-y-4">
                    @if(str_ends_with(strtolower($purchase->invoice_image_path), '.pdf'))
                        <a href="{{ route('purchases.invoice', $purchase) }}" target="_blank"
                            class="block border border-miga rounded-md p-8 text-center text-sm text-masa-madre hover:border-horno transition-colors">
                            Abrir PDF de la factura →
                        </a>
                    @else
                        <img src="{{ route('purchases.invoice', $purchase) }}" alt="Factura"
                            class="rounded-md border border-miga max-h-[70vh] w-full object-contain bg-miga/30">
                    @endif
                    <div class="flex justify-end">
                        <a href="{{ route('purchases.invoice', $purchase) }}" download
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Descargar
                        </a>
                    </div>
                </div>
            </x-crud-modal>
        @endif

    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="title">
        {{ $purchase->invoice_number ? 'Factura #' . $purchase->invoice_number : 'Compra #' . $purchase->id }}
    </x-slot>

    @php
        $errorsInAddLine = $errors->hasAny(['raw_name', 'quantity_purchased', 'purchase_unit', 'unit_price']) && old('_form') === 'add-line';
        $editingLine = null;
        $errorsInEditLine = $errors->hasAny(['quantity_purchased', 'purchase_unit', 'unit_price']) && old('_form') === 'edit-line';
        if ($errorsInEditLine) {
            $editingLine = ['id' => old('line_id'), 'raw_name' => old('raw_name'), 'quantity_purchased' => old('quantity_purchased'), 'purchase_unit' => old('purchase_unit'), 'unit_price' => old('unit_price'), 'iva_rate' => old('iva_rate'), 'percepcion_rate' => old('percepcion_rate')];
        }
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
                        <h2 class="text-base font-semibold text-corteza">
                            {{ $purchase->invoice_number ? 'Factura N° ' . $purchase->invoice_number : 'Compra sin número de factura' }}
                        </h2>
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
                <h3 class="text-sm font-semibold text-corteza mb-3">Renglones de la factura</h3>

                @if($purchase->lines->isEmpty())
                    <div class="bg-white rounded-lg shadow p-6 text-center text-masa-madre text-sm">
                        No hay renglones. Agregá uno abajo o cargá una factura.
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
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-masa-madre">
                        Esta es la factura tal como se leyó. La asociación con insumos y la actualización de costos se hacen en un paso aparte.
                    </p>
                @endif
            </div>

            {{-- Agregar renglón manual --}}
            @can('manage-costs')
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-sm font-semibold text-corteza mb-4">Agregar renglón</h3>

                <form method="POST" action="{{ route('purchases.lines.store', $purchase) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_form" value="add-line">

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="sm:col-span-2">
                            <x-input-label value="Descripción" />
                            <x-text-input name="raw_name" type="text"
                                class="mt-1 block w-full"
                                :value="old('raw_name')"
                                required />
                            <x-input-error :messages="$errors->get('raw_name')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Cantidad" />
                            <x-text-input name="quantity_purchased" type="number"
                                step="0.0001" min="0.0001"
                                data-maxdecimals="4"
                                class="mt-1 block w-full"
                                :value="old('quantity_purchased')"
                                required />
                            <x-input-error :messages="$errors->get('quantity_purchased')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Unidad" />
                            <select name="purchase_unit" required
                                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                @foreach($units as $unit)
                                    <option value="{{ $unit->value }}" @selected(old('purchase_unit') === $unit->value)>
                                        {{ $unit->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('purchase_unit')" class="mt-1" />
                        </div>
                    </div>

                    @php
                        $defaultIva = old('iva_rate', $purchase->default_iva_rate !== null ? (string) $purchase->default_iva_rate : '0.21');
                        $defaultPercepcion = old('percepcion_rate', $purchase->default_percepcion_rate !== null ? (string) $purchase->default_percepcion_rate : '');
                    @endphp
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                        <div>
                            <x-input-label value="Precio unitario" />
                            <div class="relative mt-1">
                                <span class="absolute inset-y-0 left-3 flex items-center text-masa-madre text-sm">$</span>
                                <x-text-input name="unit_price" type="number"
                                    step="0.0001" min="0"
                                    data-maxdecimals="4"
                                    class="block w-full pl-7"
                                    :value="old('unit_price')"
                                    required />
                            </div>
                            <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Alíc. IVA" />
                            <select name="iva_rate"
                                class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm text-sm">
                                <option value="0.21" @selected($defaultIva === '0.21')>21%</option>
                                <option value="0.105" @selected($defaultIva === '0.105' || $defaultIva === '0.1050')>10,5%</option>
                                <option value="0" @selected($defaultIva === '0' || $defaultIva === '0.0000')>0%</option>
                            </select>
                        </div>

                        <div>
                            <x-input-label value="Percepción" />
                            <div class="relative mt-1">
                                <x-text-input name="percepcion_rate" type="number"
                                    step="0.01" min="0" max="100"
                                    class="block w-full pr-8"
                                    :value="$defaultPercepcion"
                                    placeholder="0" />
                                <span class="absolute inset-y-0 right-3 flex items-center text-masa-madre text-sm">%</span>
                            </div>
                        </div>

                        <div class="flex items-end">
                            <x-primary-button>Agregar renglón</x-primary-button>
                        </div>
                    </div>

                    <p class="text-xs text-masa-madre">
                        Esto solo agrega un renglón a la factura digitalizada. La asociación con insumos y el costo se hacen aparte.
                    </p>

                    @if($errorsInAddLine)
                        <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded text-xs">
                            Revisá los campos marcados arriba.
                        </div>
                    @endif
                </form>
            </div>
            @endcan

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

<x-app-layout>
    <x-slot name="title">Compras</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['supplier_id', 'invoice_number', 'invoice_date', 'notes', 'invoice']) && old('_form') === 'create';
        $showUrl = fn ($purchase) => route('purchases.show', array_filter(['purchase' => $purchase->id, 'volver' => request()->getQueryString()]));
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{ mobileExpanded: false }">

        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Compras</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Facturas de compra de insumos y envases.</p>
                </div>
                @can('manage-costs')
                    <div class="flex items-center gap-2">
                        <a href="{{ route('purchases.scan.create') }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Leer factura
                        </a>
                        <button type="button"
                            @click="$dispatch('open-modal', 'purchase-create')"
                            class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                            + Nueva compra
                        </button>
                    </div>
                @endcan
            </div>

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
                <div>
                    <label class="block text-xs font-medium text-masa-madre mb-1">Buscar</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="N° factura o proveedor…"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno w-48">
                </div>
                <div>
                    <label class="block text-xs font-medium text-masa-madre mb-1">Proveedor</label>
                    <select name="supplier_id"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todos</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                @selected(request('supplier_id') == $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-masa-madre mb-1">Desde</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <div>
                    <label class="block text-xs font-medium text-masa-madre mb-1">Hasta</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('supplier_id') || request('from') || request('to') || request('search'))
                    <a href="{{ route('purchases.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @php
                $sort = request('sort', 'date');
                $dir  = request('dir', 'desc');
                $sortUrl = fn (string $col): string => request()->url() . '?' . http_build_query(
                    array_merge(request()->except(['sort', 'dir', 'page']), [
                        'sort' => $col,
                        'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
                    ])
                );
                $sortIcon = fn (string $col): string => $sort === $col ? ($dir === 'asc' ? '↑' : '↓') : '';
            @endphp

            @if($purchases->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    @if(request('supplier_id') || request('from') || request('to'))
                        No se encontraron compras con esos filtros.
                    @else
                        Todavía no hay compras registradas. Cargá la primera factura.
                    @endif
                </div>
            @else
                {{-- Cards (mobile) --}}
                <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="space-y-3">
                    @foreach($purchases as $purchase)
                        @php
                            $cardTotal = $includeIva
                                ? ($purchase->invoice_total ?? $purchase->net_total ?? 0)
                                : ($purchase->net_total ?? 0);
                            $cardAllApplied = $purchase->lines_count > 0 && $purchase->applied_count >= $purchase->lines_count;
                        @endphp
                        <div class="bg-white border border-miga rounded-lg p-4 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium text-corteza">{{ $purchase->supplier->name }}</div>
                                    <div class="text-xs text-masa-madre mt-0.5 font-mono">
                                        {{ $purchase->invoice_date->format('d/m/Y') }}
                                        @if($purchase->invoice_number)
                                            · #{{ $purchase->invoice_number }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono font-medium text-corteza">$ {{ number_format($cardTotal, 2, ',', '.') }}</div>
                                    <div class="text-xs text-masa-madre mt-0.5">{{ $purchase->lines_count }} ítems</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-miga">
                                <a href="{{ $showUrl($purchase) }}"
                                    class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                    Ver detalle
                                </a>
                                @can('manage-costs')
                                    @if($purchase->lines_count > 0)
                                        <a href="{{ route('purchases.match', $purchase) }}"
                                            class="flex-1 py-1.5 px-3 text-sm border rounded text-center transition-colors {{ $cardAllApplied ? 'border-green-300 text-green-700 hover:bg-green-50' : 'border-amber-300 text-amber-700 hover:bg-amber-50' }}">
                                            {{ $cardAllApplied ? 'Vinculada' : 'Vincular' }}
                                        </a>
                                    @endif
                                    <form method="POST" action="{{ route('purchases.destroy', $purchase) }}"
                                        onsubmit="return confirm('¿Eliminar esta compra y todos sus renglones?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="py-1.5 px-3 text-sm border border-red-300 text-red-600 hover:bg-red-50 rounded transition-colors">
                                            Eliminar
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                    <button type="button" @click="mobileExpanded = true"
                        class="w-full py-2 text-sm text-masa-madre hover:text-corteza text-center">
                        Ver tabla completa ↓
                    </button>
                </div>

                {{-- Tabla (desktop siempre, mobile si está expandida) --}}
                <div :class="mobileExpanded ? '' : 'hidden md:block'" class="bg-white rounded-lg shadow overflow-x-auto">
                    <div class="md:hidden px-4 py-2 border-b border-miga">
                        <button type="button" @click="mobileExpanded = false"
                            class="text-sm text-masa-madre hover:text-corteza">
                            ← Volver a cards
                        </button>
                    </div>
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    <a href="{{ $sortUrl('date') }}" class="hover:text-corteza">Fecha {{ $sortIcon('date') }}</a>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <a href="{{ $sortUrl('invoice_number') }}" class="hover:text-corteza">N° Factura {{ $sortIcon('invoice_number') }}</a>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    <a href="{{ $sortUrl('supplier') }}" class="hover:text-corteza">Proveedor {{ $sortIcon('supplier') }}</a>
                                </th>
                                <th class="px-4 py-3 font-medium text-center">
                                    <a href="{{ $sortUrl('items') }}" class="hover:text-corteza">Ítems {{ $sortIcon('items') }}</a>
                                </th>
                                <th class="px-4 py-3 font-medium text-right">
                                    <a href="{{ $sortUrl('total') }}" class="hover:text-corteza">Total {{ $includeIva ? '(c/IVA)' : '(s/IVA)' }} {{ $sortIcon('total') }}</a>
                                </th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($purchases as $purchase)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-sm text-corteza">
                                        {{ $purchase->invoice_date->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre font-mono text-xs">
                                        @if($purchase->invoice_number)
                                            <a href="{{ $showUrl($purchase) }}"
                                               class="hover:underline hover:text-corteza transition-colors">
                                                {{ $purchase->invoice_number }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        {{ $purchase->supplier->name }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-masa-madre">
                                        {{ $purchase->lines_count }}
                                    </td>
                                    @php
                                        $rowTotal = $includeIva
                                            ? ($purchase->invoice_total ?? $purchase->net_total ?? 0)
                                            : ($purchase->net_total ?? 0);
                                    @endphp
                                    <td class="px-4 py-3 text-right font-mono text-corteza">
                                        ${{ number_format($rowTotal, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <a href="{{ $showUrl($purchase) }}"
                                                class="inline-flex p-1 text-masa-madre hover:text-corteza transition-colors"
                                                title="Ver detalle">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                </svg>
                                            </a>
                                            @can('manage-costs')
                                                @if($purchase->lines_count > 0)
                                                    @php
                                                        $allApplied = $purchase->applied_count >= $purchase->lines_count;
                                                    @endphp
                                                    <a href="{{ route('purchases.match', $purchase) }}"
                                                        class="inline-flex p-1 transition-colors {{ $allApplied ? 'text-green-500 hover:text-green-700' : 'text-amber-500 hover:text-amber-700' }}"
                                                        title="{{ $allApplied ? 'Todos los renglones vinculados' : ($purchase->lines_count - $purchase->applied_count) . ' renglón(es) sin vincular' }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                                        </svg>
                                                    </a>
                                                @endif
                                                <form method="POST" action="{{ route('purchases.destroy', $purchase) }}"
                                                    onsubmit="return confirm('¿Eliminar esta compra y todos sus renglones?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="inline-flex p-1 text-red-400 hover:text-red-600 transition-colors"
                                                        title="Eliminar compra">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($purchases->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $purchases->links() }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-masa-madre">{{ $purchases->total() }} compra(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('purchases.modals.create')
            @include('suppliers.modals.quick-create')
        @endcan

    </div>
</x-app-layout>

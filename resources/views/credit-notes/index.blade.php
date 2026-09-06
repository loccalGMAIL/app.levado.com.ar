<x-app-layout>
    <x-slot name="title">Notas de crédito</x-slot>

    <div class="py-8 px-6 lg:px-8" x-data="{}">

        <div class="pb-4">
            <a href="{{ route('purchases.index') }}"
                class="inline-flex items-center gap-1.5 text-sm text-masa-madre hover:text-corteza transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Volver a compras
            </a>
        </div>

        <div class="space-y-6">

            <x-list-header title="Notas de crédito" subtitle="Devoluciones y reconocimientos económicos de los proveedores.">
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'credit-note-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nueva nota de crédito
                    </button>
                @endcan
            </x-list-header>

            <x-list-filters :reset-route="route('credit-notes.index')" placeholder="N° de nota o proveedor…" :status="false">
                <select name="supplier_id"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todos los proveedores</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>
                            {{ $supplier->name }}
                        </option>
                    @endforeach
                </select>
            </x-list-filters>

            @php
                $sort = request('sort', 'date');
                $dir  = request('dir', 'desc');
            @endphp

            @if($creditNotes->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('supplier_id'))
                        No se encontraron notas de crédito con esos filtros.
                    @else
                        Todavía no hay notas de crédito registradas.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$creditNotes" total-label="nota de crédito">
                    <x-slot:head>
                        <x-sortable-th column="date" :sort="$sort" :dir="$dir">Fecha</x-sortable-th>
                        <x-sortable-th column="note_number" :sort="$sort" :dir="$dir">N° de nota</x-sortable-th>
                        <x-sortable-th column="supplier" :sort="$sort" :dir="$dir">Proveedor</x-sortable-th>
                        <th class="px-4 py-3 font-medium">Compra de origen</th>
                        <th class="px-4 py-3 font-medium text-center">Renglones</th>
                        <x-sortable-th column="total" :sort="$sort" :dir="$dir" align="right">Total</x-sortable-th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($creditNotes as $note)
                        <x-data-table.row>
                            <x-data-table.cell role="meta" class="font-mono text-sm text-corteza">
                                {{ $note->note_date->format('d/m/Y') }}
                            </x-data-table.cell>

                            <x-data-table.cell role="title" class="font-medium text-corteza">
                                <a href="{{ route('credit-notes.show', $note) }}" class="hover:underline">
                                    {{ $note->note_number ?? '#'.$note->id }}
                                </a>
                            </x-data-table.cell>

                            <x-data-table.cell role="subtitle" class="text-masa-madre">{{ $note->supplier->name }}</x-data-table.cell>

                            <x-data-table.cell role="meta" class="text-masa-madre text-xs">
                                @if($note->purchase)
                                    <a href="{{ route('purchases.show', $note->purchase) }}" class="hover:underline hover:text-corteza">
                                        {{ $note->purchase->invoice_number ?? 'Compra #'.$note->purchase->id }}
                                    </a>
                                @endif
                            </x-data-table.cell>

                            <x-data-table.cell role="meta" align="right" cards="hide">{{ $note->lines_count }}</x-data-table.cell>

                            <x-data-table.cell role="figure" align="right" class="text-corteza font-mono">
                                − $ {{ number_format($note->net_total ?? 0, 2, ',', '.') }}
                            </x-data-table.cell>

                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions">
                                        <a href="{{ route('credit-notes.show', $note) }}"
                                            aria-label="Ver detalle" title="Ver detalle"
                                            class="dt-action">
                                            <x-icon name="eye" />
                                            <span class="dt-card-only">Ver detalle</span>
                                        </a>
                                        <form method="POST" action="{{ route('credit-notes.destroy', $note) }}"
                                            onsubmit="return confirm('¿Eliminar esta nota de crédito y revertir el stock de sus renglones aplicados?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                aria-label="Eliminar nota de crédito" title="Eliminar nota de crédito"
                                                class="dt-action dt-action--danger">
                                                <x-icon name="trash" />
                                                <span class="dt-card-only">Eliminar</span>
                                            </button>
                                        </form>
                                    </div>
                                </x-data-table.cell>
                            @endcan
                        </x-data-table.row>
                    @endforeach
                </x-data-table>
            @endif

        </div>

        @can('manage-costs')
            @include('credit-notes.modals.create', ['purchase' => $lockedPurchase, 'openOnLoad' => $lockedPurchase !== null])
        @endcan

    </div>
</x-app-layout>

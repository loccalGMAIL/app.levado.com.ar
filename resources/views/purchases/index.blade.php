<x-app-layout>
    <x-slot name="title">Compras</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['supplier_id', 'invoice_number', 'invoice_date', 'notes', 'invoice']) && old('_form') === 'create';
        $showUrl = fn ($purchase) => route('purchases.show', array_filter(['purchase' => $purchase->id, 'volver' => request()->getQueryString()]));
    @endphp

    <div class="py-8 px-6 lg:px-8">

        <div class="space-y-6">

            <x-list-header title="Compras" subtitle="Facturas de compra de insumos y envases.">
                <a href="{{ route('credit-notes.index') }}"
                    class="gap-1.5 px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                    <x-icon name="receipt-refund" />
                    Notas de crédito
                </a>
                @can('manage-costs')
                    <a href="{{ route('purchases.scan.create') }}"
                        class="gap-1.5 px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        Leer factura
                    </a>
                    <button type="button"
                        @click="$dispatch('open-modal', 'perception-concepts-manage')"
                        class="px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                        Percepciones
                    </button>
                    <button type="button"
                        @click="$dispatch('open-modal', 'purchase-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nueva compra
                    </button>
                @endcan
            </x-list-header>

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
                <x-data-table :paginator="$purchases" total-label="compra">
                    <x-slot:head>
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
                    </x-slot:head>

                    @foreach($purchases as $purchase)
                        @php
                            $rowTotal = $includeIva
                                ? ($purchase->lines_gross_total ?? 0) + ($purchase->perceptions_total ?? 0)
                                : ($purchase->net_total ?? 0);
                            $allResolved = $purchase->lines_count > 0 && $purchase->resolved_count >= $purchase->lines_count;
                        @endphp
                        <x-data-table.row>
                            <x-data-table.cell role="subtitle" class="font-mono text-sm text-corteza">
                                {{ $purchase->invoice_date->format('d/m/Y') }}
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre font-mono text-xs">
                                @if($purchase->invoice_number)
                                    <a href="{{ $showUrl($purchase) }}" class="hover:underline hover:text-corteza transition-colors">
                                        <span class="dt-card-only">#</span>{{ $purchase->invoice_number }}
                                    </a>
                                @endif
                            </x-data-table.cell>
                            <x-data-table.cell role="title">
                                <span class="font-medium text-corteza">{{ $purchase->supplier->name }}</span>
                            </x-data-table.cell>
                            <x-data-table.cell role="meta" class="md:text-center text-masa-madre">
                                {{ $purchase->lines_count }}<span class="dt-card-only"> ítems</span>
                            </x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" class="font-mono text-corteza [overflow-wrap:anywhere]">
                                ${{ number_format($rowTotal, 2, ',', '.') }}
                            </x-data-table.cell>
                            <x-data-table.cell role="actions">
                                <div class="dt-actions dt-actions--grid">
                                    <a href="{{ $showUrl($purchase) }}" title="Ver detalle" aria-label="Ver detalle"
                                        class="dt-action">
                                        <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-pencil"></use></svg>
                                        <span class="dt-card-only">Ver detalle</span>
                                    </a>
                                    @can('manage-costs')
                                        @if($purchase->lines_count > 0)
                                            <a href="{{ route('purchases.match', $purchase) }}"
                                                class="dt-action {{ $allResolved ? 'text-green-600 hover:text-green-700' : 'text-amber-600 hover:text-amber-700' }}"
                                                aria-label="{{ $allResolved ? 'Vinculada' : 'Vincular' }}"
                                                title="{{ $allResolved ? 'No queda ningún renglón por resolver' : ($purchase->lines_count - $purchase->resolved_count) . ' renglón(es) sin resolver' }}">
                                                <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-link"></use></svg>
                                                <span class="dt-card-only">{{ $allResolved ? 'Vinculada' : 'Vincular' }}</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('credit-notes.index', ['purchase_id' => $purchase->id]) }}"
                                            class="dt-action" title="Nota de crédito" aria-label="Nota de crédito">
                                            <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-receipt-refund"></use></svg>
                                            <span class="dt-card-only">Nota de crédito</span>
                                        </a>
                                        <form method="POST" action="{{ route('purchases.destroy', $purchase) }}"
                                            onsubmit="return confirm('¿Eliminar esta compra y todos sus renglones?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dt-action dt-action--danger"
                                                title="Eliminar compra" aria-label="Eliminar compra">
                                                <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-trash"></use></svg>
                                                <span class="dt-card-only">Eliminar</span>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </x-data-table.cell>
                        </x-data-table.row>
                    @endforeach
                </x-data-table>
            @endif

        </div>

        @can('manage-costs')
            @include('purchases.modals.create')
            @include('suppliers.modals.quick-create')
            @include('purchases.modals.perception-concepts')
            @include('purchases.modals.perception-concepts-manage')
        @endcan

    </div>
</x-app-layout>

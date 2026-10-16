<x-app-layout>
    <x-slot name="title">Stock</x-slot>

    @php
        $stockErrorForms = ['stock-adjust', 'stock-count', 'stock-min'];
        $errorsInStockModal = in_array(old('_form'), $stockErrorForms, true) && $errors->any();
        $selectedDefault = ['type' => $type, 'id' => null, 'name' => '', 'unit' => '', 'qty' => 0, 'min' => null];
        $selectedOnError = $errorsInStockModal ? [
            'type' => old('stock_type', $type),
            'id'   => old('stock_id'),
            'name' => old('stock_name', ''),
            'unit' => old('stock_unit', ''),
            'qty'  => (float) old('stock_qty', 0),
            'min'  => old('stock_min') !== null && old('stock_min') !== '' ? (float) old('stock_min') : null,
        ] : $selectedDefault;

        $displayUnit = fn ($item) => $item->subdivisions && $item->subdivision_label
            ? $item->subdivision_label
            : (in_array($type, ['ingredient', 'product'], true) ? $item->unit->short() : 'u');

        // El elaborado deriva su costo de la receta (Product::currentCost()); insumos/descartables usan su cost_per_unit.
        $unitCost = fn ($item) => $type === 'product'
            ? ($item->currentCost() ?? 0)
            : (float) $item->cost_per_unit;

        $rowPayload = fn ($item, $level) => [
            'type' => $type,
            'id'   => $item->id,
            'name' => $item->name,
            'unit' => $displayUnit($item),
            'qty'  => $level !== null ? round((float) $level->quantity, 4) : 0,
            'min'  => $level?->min_quantity !== null ? round((float) $level->min_quantity, 4) : null,
        ];

        $tabUrl = fn (string $tab) => route('stock.index', array_filter(['type' => $tab, 'search' => request('search')]));

        $sort = request('sort', 'name');
        $dir  = request('dir', 'asc');
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            selected: {{ Js::from($selectedOnError) }},
            openAction(record, modal) {
                this.selected = record;
                $dispatch('open-modal', modal);
            }
        }">

        <div class="space-y-6">

            <x-list-header title="Stock" :subtitle="'Stock de insumos, descartables y productos en '.$location->name.'.'" />

            {{-- Tabs insumos / descartables --}}
            <div class="flex gap-1 border-b border-miga">
                <a href="{{ $tabUrl('ingredient') }}"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ $type === 'ingredient' ? 'border-horno text-horno' : 'border-transparent text-masa-madre hover:text-corteza' }}">
                    Insumos
                </a>
                <a href="{{ $tabUrl('packaging') }}"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ $type === 'packaging' ? 'border-horno text-horno' : 'border-transparent text-masa-madre hover:text-corteza' }}">
                    Descartables
                </a>
                <a href="{{ $tabUrl('product') }}"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ $type === 'product' ? 'border-horno text-horno' : 'border-transparent text-masa-madre hover:text-corteza' }}">
                    Productos
                </a>
            </div>

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search'))
                    <a href="{{ route('stock.index', ['type' => $type]) }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @if($items->isEmpty())
                <x-empty-state>
                    @if(request('search'))
                        No se encontraron ítems con esos filtros.
                    @else
                        Todavía no hay {{ ['ingredient' => 'insumos', 'packaging' => 'descartables', 'product' => 'productos'][$type] ?? 'ítems' }} activos para mostrar.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$items" total-label="ítem" total-note="La valuación usa el último costo de cada ítem.">
                    <x-slot:head>
                        <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                        <x-sortable-th column="quantity" :sort="$sort" :dir="$dir" align="right">Stock actual</x-sortable-th>
                        <x-sortable-th column="min_quantity" :sort="$sort" :dir="$dir" align="right">Mínimo</x-sortable-th>
                        <th class="px-4 py-3 font-medium text-right">Valuación</th>
                        <th class="px-4 py-3 font-medium">Alerta</th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($items as $item)
                        @php
                            $level = $levels->get($item->id);
                            $qty = $level !== null ? (float) $level->quantity : 0.0;
                            $payload = $rowPayload($item, $level);
                        @endphp
                        <x-data-table.row>
                            <x-data-table.cell role="title">
                                <a href="{{ route('stock.show', [$type, $item->id]) }}" class="font-medium text-corteza hover:underline">{{ $item->name }}</a>
                            </x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" label="Stock:" class="font-mono {{ $qty < 0 ? 'text-red-600' : 'text-corteza' }}">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    {{ number_format($qty, 2, ',', '.') }}
                                    <span class="text-xs text-masa-madre font-normal">{{ $displayUnit($item) }}</span>
                                    <a href="{{ route('stock.show', [$type, $item->id]) }}"
                                        class="text-masa-madre hover:text-corteza" title="Ver movimientos de stock">
                                        <svg class="ico w-3.5 h-3.5" aria-hidden="true"><use href="{{ $iconSprite }}#i-clock"></use></svg>
                                    </a>
                                </div>
                            </x-data-table.cell>
                            <x-data-table.cell role="meta" align="right" label="Mínimo:" class="font-mono text-masa-madre text-xs">
                                @if($level?->min_quantity !== null)
                                    {{ number_format($level->min_quantity, 2, ',', '.') }}<span class="dt-card-only"> {{ $displayUnit($item) }}</span>
                                @endif
                            </x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" label="Valuación:" class="font-mono text-corteza">
                                $ {{ number_format($qty * $unitCost($item), 2, ',', '.') }}
                            </x-data-table.cell>
                            <x-data-table.cell role="badge" dash>
                                @if($qty < 0)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Negativo</span>
                                @elseif($level !== null && $level->hasAlert())
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Bajo mínimo</span>
                                @endif
                            </x-data-table.cell>
                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions dt-actions--grid">
                                        <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-adjust')"
                                            class="dt-action border border-gray-300 !text-corteza text-xs">Ajuste</button>
                                        <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-count')"
                                            class="dt-action border border-gray-300 !text-corteza text-xs">Recuento</button>
                                        <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-min')"
                                            class="dt-action border border-gray-300 !text-corteza text-xs">Mínimo</button>
                                    </div>
                                </x-data-table.cell>
                            @endcan
                        </x-data-table.row>
                    @endforeach
                </x-data-table>
            @endif

        </div>

        @can('manage-costs')
            @include('stock.modals.adjust')
            @include('stock.modals.count')
            @include('stock.modals.min')
        @endcan

    </div>
</x-app-layout>

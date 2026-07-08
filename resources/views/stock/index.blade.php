<x-app-layout>
    <x-slot name="title">Existencias</x-slot>

    @php
        $stockErrorForms = ['stock-adjust', 'stock-waste', 'stock-count', 'stock-min'];
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
            : ($type === 'ingredient' ? $item->unit->short() : 'u');

        $rowPayload = fn ($item, $level) => [
            'type' => $type,
            'id'   => $item->id,
            'name' => $item->name,
            'unit' => $displayUnit($item),
            'qty'  => $level !== null ? round((float) $level->quantity, 4) : 0,
            'min'  => $level?->min_quantity !== null ? round((float) $level->min_quantity, 4) : null,
        ];

        $tabUrl = fn (string $tab) => route('stock.index', array_filter(['type' => $tab, 'search' => request('search')]));
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            mobileExpanded: false,
            selected: {{ Js::from($selectedOnError) }},
            openAction(record, modal) {
                this.selected = record;
                $dispatch('open-modal', modal);
            }
        }">

        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Existencias</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Stock de insumos y descartables en {{ $location->name }}.</p>
                </div>
            </div>

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
            </div>

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <input type="hidden" name="type" value="{{ $type }}">
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
                        Todavía no hay {{ $type === 'ingredient' ? 'insumos' : 'descartables' }} activos para mostrar.
                    @endif
                </x-empty-state>
            @else
                {{-- Cards (mobile) --}}
                <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="space-y-3">
                    @foreach($items as $item)
                        @php
                            $level = $levels->get($item->id);
                            $qty = $level !== null ? (float) $level->quantity : 0.0;
                            $payload = $rowPayload($item, $level);
                        @endphp
                        <div class="bg-white border border-miga rounded-lg p-4 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <a href="{{ route('stock.show', [$type, $item->id]) }}" class="font-medium text-corteza hover:underline">{{ $item->name }}</a>
                                    <div class="text-xs text-masa-madre mt-0.5">
                                        Mínimo: {{ $level?->min_quantity !== null ? number_format($level->min_quantity, 2, ',', '.').' '.$displayUnit($item) : '—' }}
                                    </div>
                                </div>
                                @if($qty < 0)
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Negativo</span>
                                @elseif($level !== null && $level->hasAlert())
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Bajo mínimo</span>
                                @endif
                            </div>
                            <div class="mt-2 flex items-baseline justify-between">
                                <span class="text-sm font-mono {{ $qty < 0 ? 'text-red-600' : 'text-corteza' }}">
                                    {{ number_format($qty, 2, ',', '.') }} <span class="text-xs text-masa-madre">{{ $displayUnit($item) }}</span>
                                </span>
                                <span class="text-xs text-masa-madre font-mono">$ {{ number_format($qty * (float) $item->cost_per_unit, 2, ',', '.') }}</span>
                            </div>
                            @can('manage-costs')
                                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-miga text-sm">
                                    <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-adjust')"
                                        class="flex-1 py-1.5 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Ajuste</button>
                                    <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-waste')"
                                        class="flex-1 py-1.5 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Merma</button>
                                    <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-count')"
                                        class="flex-1 py-1.5 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Recuento</button>
                                </div>
                            @endcan
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
                                <th class="px-4 py-3 font-medium">Nombre</th>
                                <th class="px-4 py-3 font-medium text-right">Stock actual</th>
                                <th class="px-4 py-3 font-medium text-right">Mínimo</th>
                                <th class="px-4 py-3 font-medium text-right">Valuación</th>
                                <th class="px-4 py-3 font-medium">Alerta</th>
                                @can('manage-costs')
                                    <th class="px-4 py-3"></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($items as $item)
                                @php
                                    $level = $levels->get($item->id);
                                    $qty = $level !== null ? (float) $level->quantity : 0.0;
                                    $payload = $rowPayload($item, $level);
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        <a href="{{ route('stock.show', [$type, $item->id]) }}" class="hover:underline">{{ $item->name }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono {{ $qty < 0 ? 'text-red-600' : 'text-corteza' }}">
                                        {{ number_format($qty, 2, ',', '.') }}
                                        <span class="text-xs text-masa-madre font-normal">{{ $displayUnit($item) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-masa-madre text-xs">
                                        {{ $level?->min_quantity !== null ? number_format($level->min_quantity, 2, ',', '.') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-corteza">
                                        $ {{ number_format($qty * (float) $item->cost_per_unit, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($qty < 0)
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Negativo</span>
                                        @elseif($level !== null && $level->hasAlert())
                                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Bajo mínimo</span>
                                        @else
                                            <span class="text-xs text-masa-madre">—</span>
                                        @endif
                                    </td>
                                    @can('manage-costs')
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-2 text-xs">
                                                <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-adjust')"
                                                    class="px-2 py-1 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Ajuste</button>
                                                <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-waste')"
                                                    class="px-2 py-1 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Merma</button>
                                                <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-count')"
                                                    class="px-2 py-1 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Recuento</button>
                                                <button type="button" @click="openAction({{ Js::from($payload) }}, 'stock-min')"
                                                    class="px-2 py-1 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Mínimo</button>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($items->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $items->links() }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-masa-madre">{{ $items->total() }} ítem(s) en total. La valuación usa el último costo de cada ítem.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('stock.modals.adjust')
            @include('stock.modals.waste')
            @include('stock.modals.count')
            @include('stock.modals.min')
        @endcan

    </div>
</x-app-layout>

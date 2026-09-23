<x-app-layout>
    <x-slot name="title">Pedidos recurrentes</x-slot>

    @php
        // Chips de días: se resalta el día si está en weekdays. Mismo orden
        // ISO que en el modal de alta (Lun→Dom), 'M' se repite (martes y
        // miércoles) — por eso es una lista, no un array asociativo.
        $weekdayLabels = [[1, 'L'], [2, 'M'], [3, 'M'], [4, 'J'], [5, 'V'], [6, 'S'], [7, 'D']];
    @endphp

    {{-- products viaja una sola vez acá (root x-data), igual que en
         production-orders/index.blade.php — el modal de edición lo lee por
         referencia. --}}
    <div class="py-8 px-6 lg:px-8" x-data="{ editing: null, products: @js($products) }">
        <div class="space-y-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Órdenes de producción</h2>
                    <p class="text-sm text-masa-madre mt-0.5">
                        Se generan solos, hasta 7 días adelante, cada vez que alguien entra a Órdenes.
                        @if($tenant->recurring_materialized_at)
                            Última generación: {{ $tenant->recurring_materialized_at->format('d/m/Y H:i') }}.
                        @endif
                    </p>
                </div>
                @can('manage-costs')
                    <form method="POST" action="{{ route('production-requests.recurring.generate') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                            Generar ahora
                        </button>
                    </form>
                @endcan
            </div>

            @include('production-orders.tabs')

            @if($recurring->isEmpty())
                <x-empty-state>
                    Todavía no hay pedidos recurrentes. Se crean tildando "Se repite" al cargar un pedido nuevo.
                </x-empty-state>
            @else
                <div class="bg-white border border-miga rounded-lg shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Destino</th>
                                <th class="px-4 py-3 text-left font-medium">Días</th>
                                <th class="px-4 py-3 text-left font-medium">Artículos</th>
                                <th class="px-4 py-3 text-left font-medium">Vigencia</th>
                                <th class="px-4 py-3 text-left font-medium">Estado</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($recurring as $item)
                                <tr class="{{ $item->active ? '' : 'opacity-60' }}">
                                    <td class="px-4 py-3 text-corteza">
                                        {{ $item->destination?->name ?? '—' }}
                                        <div class="text-xs text-masa-madre">{{ $item->destination_type->label() }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-1">
                                            @foreach($weekdayLabels as [$iso, $label])
                                                <span class="w-5 h-5 flex items-center justify-center rounded text-[11px] {{ in_array($iso, $item->weekdays, true) ? 'bg-corteza text-white' : 'bg-miga text-masa-madre' }}">
                                                    {{ $label }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-corteza">
                                        {{ $item->lines->count() }} artículo(s)
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre text-xs whitespace-nowrap">
                                        Desde {{ $item->starts_on->format('d/m/Y') }}
                                        @if($item->ends_on)
                                            <br>hasta {{ $item->ends_on->format('d/m/Y') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-xs px-2 py-0.5 rounded-full {{ $item->active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-masa-madre' }}">
                                            {{ $item->active ? 'Activo' : 'Pausado' }}
                                        </span>
                                        @if(($item->destination?->active ?? false) === false)
                                            <div class="text-xs text-amber-700 mt-1">Destino de baja: no genera</div>
                                        @endif
                                    </td>
                                    @can('manage-costs')
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            {{-- editing = null primero: si ya había otro registro cargado,
                                                 fuerza que <template x-if="editing"> desmonte y remonte el
                                                 x-data de la grilla con los datos nuevos — si sólo se
                                                 reasigna editing sin pasar por null, x-if nunca deja de ser
                                                 verdadero y la grilla se queda con las líneas del anterior. --}}
                                            <button type="button"
                                                @click="editing = null; $nextTick(() => { editing = {{ Js::from([
                                                    'id' => $item->id,
                                                    'weekdays' => $item->weekdays,
                                                    'ends_on' => $item->ends_on?->toDateString() ?? '',
                                                    'notes' => $item->notes ?? '',
                                                    'lines' => $item->lines->map(fn ($line) => [
                                                        'id' => null,
                                                        'product_id' => $line->product_id,
                                                        'name' => $line->product->name ?? '—',
                                                        'unit' => $line->unit->short(),
                                                        'quantity' => (float) $line->quantity,
                                                    ]),
                                                    'updateUrl' => route('production-requests.recurring.update', $item),
                                                ]) }}; $dispatch('open-modal', 'production-recurring-edit'); })"
                                                class="text-sm text-horno hover:underline">
                                                Editar
                                            </button>
                                            <form method="POST" action="{{ route('production-requests.recurring.toggle-active', $item) }}" class="inline">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="text-sm text-masa-madre hover:text-corteza hover:underline ml-3">
                                                    {{ $item->active ? 'Pausar' : 'Reanudar' }}
                                                </button>
                                            </form>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @can('manage-costs')
            @include('production-orders.modals.recurring-edit', ['products' => $products])
        @endcan
    </div>
</x-app-layout>

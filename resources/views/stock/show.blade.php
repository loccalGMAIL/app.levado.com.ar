<x-app-layout>
    <x-slot name="title">Kardex — {{ $item->name }}</x-slot>

    @php
        $displayUnit = $item->subdivisions && $item->subdivision_label
            ? $item->subdivision_label
            : ($type === 'ingredient' ? $item->unit->short() : 'u');

        $qty = $level !== null ? (float) $level->quantity : 0.0;

        $selectedItem = [
            'type' => $type,
            'id'   => $item->id,
            'name' => $item->name,
            'unit' => $displayUnit,
            'qty'  => round($qty, 4),
            'min'  => $level?->min_quantity !== null ? round((float) $level->min_quantity, 4) : null,
        ];

        $typeBadge = fn ($movementType) => match ($movementType->value) {
            'purchase' => 'bg-green-100 text-green-700',
            'count' => 'bg-amber-100 text-amber-700',
            default => 'bg-blue-100 text-blue-700',
        };
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{ selected: {{ Js::from($selectedItem) }} }">

        <div class="space-y-6">

            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="space-y-1">
                    <div class="text-sm text-masa-madre">
                        <a href="{{ route('stock.index', ['type' => $type]) }}" class="hover:text-corteza transition-colors">← Existencias</a>
                    </div>
                    <h2 class="text-base font-semibold text-corteza mt-2">{{ $item->name }}</h2>
                    <p class="text-sm text-masa-madre">Kardex en {{ $location->name }}.</p>
                </div>
                @can('manage-costs')
                    <div class="flex items-center gap-2 text-sm">
                        <button type="button" @click="$dispatch('open-modal', 'stock-adjust')"
                            class="px-3 py-1.5 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Ajuste</button>
                        <button type="button" @click="$dispatch('open-modal', 'stock-count')"
                            class="px-3 py-1.5 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Recuento</button>
                        <button type="button" @click="$dispatch('open-modal', 'stock-min')"
                            class="px-3 py-1.5 border border-gray-300 rounded text-corteza hover:bg-miga transition-colors">Mínimo</button>
                    </div>
                @endcan
            </div>

            {{-- Resumen --}}
            {{-- El 4-up arranca en lg y no en md: a 768px las cuatro columnas dejan
                 87px por card, donde no entra ninguna valuación de 8 dígitos. --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="kpi-card bg-white border border-miga rounded-lg p-4 shadow-sm">
                    <p class="text-xs text-masa-madre">Stock actual</p>
                    <p class="mt-1 font-mono kpi-figure [--kpi-figure-min:0.75rem] [--kpi-figure-max:1.125rem] {{ $qty < 0 ? 'text-red-600' : 'text-corteza' }}">
                        {{ number_format($qty, 2, ',', '.') }} <span class="text-xs text-masa-madre">{{ $displayUnit }}</span>
                    </p>
                </div>
                <div class="kpi-card bg-white border border-miga rounded-lg p-4 shadow-sm">
                    <p class="text-xs text-masa-madre">Mínimo</p>
                    <p class="mt-1 font-mono kpi-figure [--kpi-figure-min:0.75rem] [--kpi-figure-max:1.125rem] text-corteza">
                        {{ $level?->min_quantity !== null ? number_format($level->min_quantity, 2, ',', '.') : '—' }}
                    </p>
                </div>
                {{-- La valuación es el número más largo de las cuatro: mientras el grid
                     sea de dos columnas toma las dos para que entre completo. --}}
                <div class="col-span-2 lg:col-span-1 kpi-card bg-white border border-miga rounded-lg p-4 shadow-sm">
                    <p class="text-xs text-masa-madre">Valuación</p>
                    <p class="mt-1 font-mono kpi-figure [--kpi-figure-min:0.75rem] [--kpi-figure-max:1.125rem] text-corteza">$ {{ number_format($qty * (float) $item->cost_per_unit, 2, ',', '.') }}</p>
                </div>
                <div class="bg-white border border-miga rounded-lg p-4 shadow-sm">
                    <p class="text-xs text-masa-madre">Estado</p>
                    <p class="mt-1">
                        @if($qty < 0)
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Stock negativo</span>
                        @elseif($level !== null && $level->hasAlert())
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Bajo mínimo</span>
                        @else
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">OK</span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Historial --}}
            @if($movements->isEmpty())
                <x-empty-state>
                    Todavía no hay movimientos de stock para este ítem. Registrá un recuento para cargar el stock inicial.
                </x-empty-state>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">Fecha</th>
                                <th class="px-4 py-3 font-medium">Tipo</th>
                                <th class="px-4 py-3 font-medium text-right">Cantidad</th>
                                <th class="px-4 py-3 font-medium text-right">Costo unit.</th>
                                <th class="px-4 py-3 font-medium">Motivo</th>
                                <th class="px-4 py-3 font-medium">Usuario</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($movements as $movement)
                                <tr>
                                    <td class="px-4 py-3 text-masa-madre whitespace-nowrap">
                                        {{ $movement->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $typeBadge($movement->type) }}">
                                            {{ $movement->type->label() }}
                                        </span>
                                        @if($movement->isReversal())
                                            <span class="block mt-0.5 text-xs text-masa-madre">reversa de #{{ $movement->reverses_movement_id }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono {{ (float) $movement->quantity < 0 ? 'text-red-600' : 'text-green-700' }}">
                                        {{ (float) $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity, 2, ',', '.') }}
                                        <span class="text-xs text-masa-madre font-normal">{{ $displayUnit }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-masa-madre text-xs">
                                        $ {{ number_format($movement->unit_cost, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">
                                        @php
                                            // Sólo las entradas por compra tienen factura de origen; el resto
                                            // (ajustes, recuentos, contramovimientos) no lleva link.
                                            $purchase = $movement->isFromPurchaseLine()
                                                ? $movement->purchaseLine?->purchase
                                                : null;
                                        @endphp
                                        @if($purchase)
                                            <a href="{{ route('purchases.show', $purchase) }}"
                                                class="text-corteza underline hover:text-horno transition-colors">
                                                Compra{{ $purchase->invoice_number ? ' #'.$purchase->invoice_number : '' }}
                                                @if($purchase->supplier)
                                                    · {{ $purchase->supplier->name }}
                                                @endif
                                            </a>
                                        @elseif($movement->reason)
                                            {{ $movement->reason }}
                                        @elseif($movement->isFromPurchaseLine())
                                            Compra (renglón #{{ $movement->reference_id }})
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">
                                        {{ $movement->user?->name ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($movements->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $movements->links() }}
                        </div>
                    @endif
                </div>
            @endif

        </div>

        @can('manage-costs')
            @include('stock.modals.adjust')
            @include('stock.modals.count')
            @include('stock.modals.min')
        @endcan

    </div>
</x-app-layout>

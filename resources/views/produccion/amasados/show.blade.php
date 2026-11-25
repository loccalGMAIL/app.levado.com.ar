<x-app-layout>
    <x-slot name="title">Amasado #{{ $batch->number }}</x-slot>

    @php
        $qty = fn (float $value) => \App\Services\QuantityFormatter::format($value);
        $money = fn (float $value) => '$ '.number_format($value, 2, ',', '.');
        $wasteTotal = $batch->lines->sum(fn ($line) => $line->wasteCost());
    @endphp

    <div class="py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl space-y-5">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <a href="{{ route('produccion.amasado.index', ['fecha' => $batch->dough_date->toDateString()]) }}"
                        class="text-sm text-masa-madre hover:text-corteza">← Amasado del {{ $batch->dough_date->format('d/m') }}</a>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-corteza">Amasado #{{ $batch->number }}</h2>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $batch->status->badgeClasses() }}">{{ $batch->status->label() }}</span>
                    </div>
                    <p class="text-sm text-masa-madre">
                        {{ $batch->dough_date->format('d/m/Y') }}
                        @if($batch->confirmed_at)
                            · confirmado {{ $batch->confirmed_at->format('d/m H:i') }}{{ $batch->confirmedBy ? ' por '.$batch->confirmedBy->name : '' }}
                        @endif
                        @if($batch->cancelled_at)
                            · anulado {{ $batch->cancelled_at->format('d/m H:i') }}{{ $batch->cancelledBy ? ' por '.$batch->cancelledBy->name : '' }}
                        @endif
                    </p>
                </div>
                @can('manage-costs')
                    @if($batch->isConfirmed())
                        <form method="POST" action="{{ route('produccion.amasados.cancel', $batch) }}"
                            onsubmit="return confirm('Se revierten todos los movimientos de este amasado. ¿Anular?')">
                            @csrf
                            <button type="submit" class="px-4 py-2 border border-red-200 text-sm text-red-600 rounded-md hover:bg-red-50 transition-colors">Anular</button>
                        </form>
                    @endif
                @endcan
            </div>

            <section class="bg-superficie rounded-lg shadow">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-masa-madre text-left border-b border-miga">
                            <tr>
                                <th class="px-4 py-2 font-medium">Artículo</th>
                                <th class="px-3 py-2 font-medium">Destino</th>
                                <th class="px-3 py-2 font-medium text-right">Planeado</th>
                                <th class="px-3 py-2 font-medium text-right">Real</th>
                                <th class="px-4 py-2 font-medium text-right">Merma</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($batch->lines as $line)
                                <tr>
                                    <td class="px-4 py-2 text-corteza">{{ $line->product->name }}</td>
                                    <td class="px-3 py-2 text-masa-madre">{{ $line->goes_to_chamber ? 'Cámara' : 'Terminado' }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-masa-madre">{{ $qty($line->planned_units) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-corteza font-semibold">{{ $qty($line->actual_units) }}</td>
                                    <td class="px-4 py-2 text-right">
                                        @if($line->waste_units > 0)
                                            <span class="font-mono text-red-600">{{ $qty($line->waste_units) }}</span>
                                            <span class="block text-xs text-masa-madre">{{ $line->waste_reason?->describe($line->waste_note) }} · {{ $money($line->wasteCost()) }}</span>
                                        @else
                                            <span class="text-masa-madre">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($wasteTotal > 0)
                    <p class="px-4 py-2 border-t border-miga text-xs text-masa-madre text-right">Merma del amasado valorizada: <span class="font-mono text-corteza">{{ $money($wasteTotal) }}</span></p>
                @endif
            </section>

            @if($batch->masses->isNotEmpty())
                <section class="bg-superficie rounded-lg shadow">
                    <header class="px-4 py-3 border-b border-miga">
                        <h3 class="text-sm font-semibold text-corteza">Masas</h3>
                    </header>
                    <ul class="divide-y divide-miga">
                        @foreach($batch->masses as $mass)
                            <li class="px-4 py-2 flex items-center justify-between gap-3 text-sm">
                                <span class="text-corteza">{{ $mass->recipe->name }}</span>
                                <span class="text-masa-madre font-mono">
                                    {{ $qty($mass->planned_quantity) }} {{ $mass->recipe->yield_unit->short() }}
                                    @if($mass->leftover_quantity > 0)
                                        <span class="text-green-700">· sobró {{ $qty($mass->leftover_quantity) }}</span>
                                    @elseif($mass->leftover_quantity < 0)
                                        <span class="text-red-600">· faltó {{ $qty(abs($mass->leftover_quantity)) }}</span>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($batch->notes)
                <p class="text-sm text-masa-madre bg-superficie rounded-lg shadow px-4 py-3">{{ $batch->notes }}</p>
            @endif

            <section class="bg-superficie rounded-lg shadow">
                <header class="px-4 py-3 border-b border-miga">
                    <h3 class="text-sm font-semibold text-corteza">Movimientos de stock</h3>
                </header>
                @if($movements->isEmpty())
                    <p class="px-4 py-4 text-sm text-masa-madre">Sin movimientos.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs text-masa-madre text-left">
                                <tr>
                                    <th class="px-4 py-2 font-medium">Ítem</th>
                                    <th class="px-3 py-2 font-medium">Ubicación</th>
                                    <th class="px-3 py-2 font-medium text-right">Cantidad</th>
                                    <th class="px-4 py-2 font-medium text-right">Costo u.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                @foreach($movements as $movement)
                                    @php $item = $movement->stockable(); @endphp
                                    <tr class="{{ $movement->isReversal() ? 'text-masa-madre' : '' }}">
                                        <td class="px-4 py-2 text-corteza">
                                            @if($item)
                                                <a href="{{ route('stock.show', ['type' => $movement->stockable_type, 'id' => $movement->stockable_id] + ($movement->location->is_default ? [] : ['location_id' => $movement->location_id])) }}" class="hover:underline">{{ $item->name }}</a>
                                            @else
                                                —
                                            @endif
                                            @if($movement->isReversal())
                                                <span class="text-xs text-masa-madre">(reversa)</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-masa-madre">{{ $movement->location->name }}</td>
                                        <td class="px-3 py-2 text-right font-mono {{ (float) $movement->quantity < 0 ? 'text-red-600' : 'text-green-700' }}">
                                            {{ (float) $movement->quantity > 0 ? '+' : '' }}{{ number_format($movement->quantity, 2, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-mono text-xs text-masa-madre">{{ $money((float) $movement->unit_cost) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

        </div>
    </div>
</x-app-layout>

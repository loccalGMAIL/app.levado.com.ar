<x-app-layout>
    <x-slot name="title">Cierre de cámara #{{ $closing->number }}</x-slot>

    @php
        $qty = fn (float $value) => \App\Services\QuantityFormatter::format($value);
        $money = fn (float $value) => '$ '.number_format($value, 2, ',', '.');
        $bakedTotal = $closing->lines->sum(fn ($line) => (float) $line->baked_units);
        $wasteTotal = $closing->lines->sum(fn ($line) => $line->waste_units * (float) $line->unit_cost);
    @endphp

    <div class="py-6 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl space-y-5">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <a href="{{ route('produccion.camara.index') }}" class="text-sm text-masa-madre hover:text-corteza">← Cierres de cámara</a>
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-corteza">Cierre de cámara #{{ $closing->number }}</h2>
                        <span class="text-[11px] font-medium px-2 py-0.5 rounded-full {{ $closing->status->badgeClasses() }}">{{ $closing->status->label() }}</span>
                    </div>
                    <p class="text-sm text-masa-madre">
                        Abierto {{ $closing->opened_at->format('d/m H:i') }}
                        @if($closing->confirmed_at)
                            · confirmado {{ $closing->confirmed_at->format('d/m H:i') }}{{ $closing->confirmedBy ? ' por '.$closing->confirmedBy->name : '' }}
                        @endif
                        @if($closing->cancelled_at)
                            · anulado {{ $closing->cancelled_at->format('d/m H:i') }}{{ $closing->cancelledBy ? ' por '.$closing->cancelledBy->name : '' }}
                        @endif
                    </p>
                </div>
                @can('manage-costs')
                    @if($closing->isConfirmed())
                        <form method="POST" action="{{ route('produccion.camara.cancel', $closing) }}"
                            onsubmit="return window.confirm('Se revierten todos los movimientos de este cierre. ¿Anular?')">
                            @csrf
                            <button type="submit" class="px-4 py-2 border border-red-200 text-sm text-red-600 rounded-md hover:bg-red-50 transition-colors">Anular</button>
                        </form>
                    @endif
                @endcan
            </div>

            <section class="bg-superficie rounded-lg shadow">
                <header class="px-4 py-3 border-b border-miga flex items-baseline justify-between gap-2">
                    <h3 class="text-sm font-semibold text-corteza">Resumen</h3>
                    <span class="text-xs text-masa-madre">Horneado: <span class="font-mono text-corteza">{{ $qty($bakedTotal) }}</span> pzs</span>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-masa-madre text-left">
                            <tr>
                                <th class="px-4 py-2 font-medium">Artículo</th>
                                <th class="px-3 py-2 font-medium text-right">Esperado</th>
                                <th class="px-3 py-2 font-medium text-right">Quedó</th>
                                <th class="px-3 py-2 font-medium text-right">Merma</th>
                                <th class="px-4 py-2 font-medium text-right">Horneado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($closing->lines as $line)
                                <tr>
                                    <td class="px-4 py-2 text-corteza">{{ $line->product->name }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-masa-madre">{{ $qty($line->expected_units) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-masa-madre">{{ $qty($line->counted_units) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        @if($line->waste_units > 0)
                                            <span class="font-mono text-red-600">{{ $qty($line->waste_units) }}</span>
                                            <span class="block text-xs text-masa-madre">{{ $line->waste_reason?->describe($line->waste_note) }}</span>
                                        @else
                                            <span class="text-masa-madre">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono font-semibold {{ (float) $line->baked_units < 0 ? 'text-red-600' : 'text-corteza' }}">
                                        {{ $qty((float) $line->baked_units) }}
                                        @if((float) $line->baked_units < 0)
                                            <span class="block text-[11px] font-normal">contado de más</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($wasteTotal > 0)
                    <p class="px-4 py-2 border-t border-miga text-xs text-masa-madre text-right">Merma de horno valorizada: <span class="font-mono text-corteza">{{ $money($wasteTotal) }}</span></p>
                @endif
            </section>

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
                                    <th class="px-3 py-2 font-medium">Tipo</th>
                                    <th class="px-3 py-2 font-medium">Ubicación</th>
                                    <th class="px-3 py-2 font-medium text-right">Cantidad</th>
                                    <th class="px-4 py-2 font-medium text-right">Costo u.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-miga">
                                @foreach($movements as $movement)
                                    @php $item = $movement->stockable(); @endphp
                                    <tr>
                                        <td class="px-4 py-2 text-corteza">{{ $item?->name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-masa-madre">{{ $movement->type->label() }}</td>
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

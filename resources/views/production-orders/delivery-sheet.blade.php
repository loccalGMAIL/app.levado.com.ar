<x-app-layout>
    <x-slot name="title">Planilla de reparto</x-slot>

    <style>
        @media print {
            nav, aside, .print\:hidden { display: none !important; }
            main { padding: 0 !important; }
            body { background: white !important; }
        }
    </style>

    <div class="py-8 px-6 lg:px-8 max-w-3xl mx-auto">

        <div class="print:hidden mb-5 flex items-center justify-between">
            <a href="{{ route('production-orders.show', $productionOrder) }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">← Volver a la orden</a>
            <button type="button" onclick="window.print()" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                Imprimir
            </button>
        </div>

        <div class="mb-6">
            <h2 class="text-lg font-semibold text-corteza">Planilla de reparto — {{ $productionOrder->numberLabel() }}</h2>
            <p class="text-sm text-masa-madre mt-0.5">
                {{ $productionOrder->type->label() }} — {{ $productionOrder->scheduled_for?->format('d/m/Y') ?? 'Sin fecha' }}
            </p>
        </div>

        @if($productionOrder->productionOrderRequests->isEmpty())
            <x-empty-state>Esta orden no tiene pedidos.</x-empty-state>
        @else
            <div class="space-y-6">
                @foreach($productionOrder->productionOrderRequests as $request)
                    <div class="border border-miga rounded-lg overflow-hidden break-inside-avoid">
                        <div class="bg-miga px-4 py-2">
                            <span class="text-xs text-masa-madre">{{ $request->numberLabel() }} · {{ $request->destination_type->label() }}</span>
                            <div class="font-semibold text-corteza">{{ $request->destination?->name ?? '—' }}</div>
                        </div>
                        @if($request->lines->isEmpty())
                            <p class="px-4 py-3 text-sm text-masa-madre">Sin artículos.</p>
                        @else
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-miga">
                                    @foreach($request->lines as $line)
                                        <tr>
                                            <td class="px-4 py-2 text-corteza">{{ $line->product?->name ?? '—' }}</td>
                                            <td class="px-4 py-2 text-right font-mono text-corteza">
                                                {{ number_format($line->quantity, 2, ',', '.') }} {{ $line->unit->short() }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</x-app-layout>

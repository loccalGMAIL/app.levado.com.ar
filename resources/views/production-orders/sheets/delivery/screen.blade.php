<x-app-layout>
    <x-slot name="title">Planilla de reparto</x-slot>

    @include('fixed-costs.report._styles')
    @include('production-orders.sheets._styles')
    <style>
        @media print {
            @page {
                margin: 1.5cm 1.2cm;
            }
            nav, aside { display: none !important; }
            main { padding: 0 !important; }
            body { background: white !important; }
        }
    </style>

    <div class="py-8 px-6 lg:px-8 print:p-0">
        <div class="max-w-3xl mx-auto space-y-5 print:max-w-none print:space-y-0">

            <div class="print:hidden flex items-center justify-between flex-wrap gap-3">
                <a href="{{ route('production-orders.show', $productionOrder) }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">
                    ← Volver a la orden
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('production-orders.delivery-sheet.pdf', array_filter(['productionOrder' => $productionOrder, 'group' => $group])) }}"
                        class="px-4 py-2 bg-white border border-gray-300 text-corteza text-sm rounded-md hover:bg-harina transition-colors">
                        Descargar PDF
                    </a>
                    <button type="button" onclick="window.print()"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        Imprimir
                    </button>
                </div>
            </div>

            @if(count($sheet['groups']) > 1 || $group)
                <div class="print:hidden flex items-center gap-1 flex-wrap text-sm">
                    <a href="{{ route('production-orders.delivery-sheet', $productionOrder) }}"
                        class="px-3 py-1 rounded-full {{ ! $group ? 'bg-corteza text-white' : 'border border-gray-300 text-corteza hover:bg-harina' }}">
                        Todos
                    </a>
                    @foreach($sheet['groups'] as $entry)
                        <a href="{{ route('production-orders.delivery-sheet', [$productionOrder, 'group' => $entry['key']]) }}"
                            class="px-3 py-1 rounded-full {{ $group === $entry['key'] ? 'bg-corteza text-white' : 'border border-gray-300 text-corteza hover:bg-harina' }}">
                            {{ $entry['title'] }}
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="bg-white rounded-lg shadow p-8 print:shadow-none print:rounded-none print:p-0">
                @include('production-orders.sheets.delivery._document', ['sheet' => $document])
            </div>

        </div>
    </div>
</x-app-layout>

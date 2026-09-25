<x-app-layout>
    <x-slot name="title">Planilla de producción</x-slot>

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
                    <a href="{{ route('production-orders.production-sheet.pdf', $productionOrder) }}"
                        class="px-4 py-2 bg-white border border-gray-300 text-corteza text-sm rounded-md hover:bg-harina transition-colors">
                        Descargar PDF
                    </a>
                    <button type="button" onclick="window.print()"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        Imprimir
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-8 print:shadow-none print:rounded-none print:p-0">
                @include('production-orders.sheets.production._document', ['sheet' => $sheet])
            </div>

        </div>
    </div>
</x-app-layout>

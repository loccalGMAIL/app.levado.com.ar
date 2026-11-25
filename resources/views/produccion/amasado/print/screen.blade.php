<x-app-layout>
    <x-slot name="title">Imprimir amasado</x-slot>

    @include('fixed-costs.report._styles')
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 1.2cm 1cm;
            }
            nav, aside { display: none !important; }
            main { padding: 0 !important; }
            body { background: white !important; }
        }
    </style>

    <div class="py-8 px-6 lg:px-8 print:p-0">
        <div class="max-w-4xl mx-auto space-y-5 print:max-w-none print:space-y-0">

            <div class="print:hidden flex items-center justify-between flex-wrap gap-3">
                <a href="{{ route('produccion.amasado.index', ['fecha' => $date->toDateString()]) }}"
                    class="text-sm text-masa-madre hover:text-corteza hover:underline">
                    ← Volver a amasado
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('produccion.amasado.imprimir.pdf', ['fecha' => $date->toDateString()]) }}"
                        class="px-4 py-2 bg-superficie border border-gray-300 text-corteza text-sm rounded-md hover:bg-harina transition-colors">
                        Descargar PDF
                    </a>
                    <button type="button" onclick="window.print()"
                        class="px-4 py-2 bg-tinta text-white text-sm rounded-md hover:bg-horno transition-colors">
                        Imprimir
                    </button>
                </div>
            </div>

            <div class="bg-superficie rounded-lg shadow p-8 print:shadow-none print:rounded-none print:p-0">
                @include('produccion.amasado.print._document', ['sheet' => $sheet])
            </div>

        </div>
    </div>
</x-app-layout>

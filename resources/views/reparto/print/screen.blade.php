<x-app-layout>
    <x-slot name="title">Imprimir reparto</x-slot>

    @include('fixed-costs.report._styles')
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 1.2cm 1cm;
            }
            nav, aside { display: none !important; }
            main { padding: 0 !important; }
            body { background: white !important; }
        }
    </style>

    <div class="py-8 px-6 lg:px-8 print:p-0">
        <div class="max-w-6xl mx-auto space-y-5 print:max-w-none print:space-y-0">

            <div class="print:hidden flex items-center justify-between flex-wrap gap-3">
                <a href="{{ route('reparto.pedidos.index') }}"
                    class="text-sm text-masa-madre hover:text-corteza hover:underline">
                    ← Volver a pedidos de clientes
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reparto.hoja.pdf', request()->only(['date', 'delivery_person'])) }}"
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
                @include('reparto.print._document', ['sheet' => $sheet])
            </div>

        </div>
    </div>
</x-app-layout>

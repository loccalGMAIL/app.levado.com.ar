<x-app-layout>
    <x-slot name="title">Reporte de gastos</x-slot>

    @include('fixed-costs.report._styles')
    <style>
        {{-- Chrome compartido del layout: se oculta sólo en esta página al imprimir.
             Los componentes que flotan fuera del flujo normal (banner PWA, bottom-nav,
             flash messages, impersonación) llevan su propio print:hidden en su markup,
             porque aparecen en cualquier pantalla, no sólo en ésta. --}}
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
        <div class="max-w-4xl mx-auto space-y-5 print:max-w-none print:space-y-0">

            <div class="print:hidden flex items-center justify-between flex-wrap gap-3">
                <a href="{{ route('fixed-costs.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">
                    ← Volver a Gastos fijos
                </a>
                <div class="flex items-center gap-2">
                    <a href="{{ route('fixed-costs.report-pdf', request()->query()) }}"
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
                @include('fixed-costs.report._document', ['report' => $report])
            </div>

        </div>
    </div>
</x-app-layout>

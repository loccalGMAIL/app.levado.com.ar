{{--
    Modal de opciones del reporte imprimible. Un solo form GET con dos envíos:
    "Ver / Imprimir" abre fixed-costs.report en una pestaña nueva (para no
    perder la pantalla de Gastos Fijos detrás); "Descargar PDF" usa
    formaction para apuntar a fixed-costs.report-pdf sin duplicar el form,
    y se queda en la misma pestaña porque una descarga no necesita una
    pestaña nueva que después quede en blanco.
--}}
@props(['from' => null, 'to' => null])

<x-crud-modal name="fixed-cost-report" title="Imprimir reporte de gastos">
    <form method="GET" action="{{ route('fixed-costs.report') }}" class="space-y-4"
          x-data="{ useFilters: false }">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="report_from" value="Desde" />
                <input type="date" id="report_from" name="from"
                    value="{{ $from ?? now()->subMonths(11)->startOfMonth()->format('Y-m-d') }}"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
            </div>
            <div>
                <x-input-label for="report_to" value="Hasta" />
                <input type="date" id="report_to" name="to"
                    value="{{ $to ?? now()->format('Y-m-d') }}"
                    class="mt-1 block w-full border-gray-300 focus:border-horno focus:ring-horno rounded-md shadow-sm">
            </div>
        </div>

        <div>
            <x-input-label value="Secciones a incluir" />
            <div class="mt-2 space-y-2">
                <label class="flex items-center gap-2 text-sm text-corteza">
                    <input type="checkbox" name="sections[]" value="current" checked
                        class="rounded border-gray-300 text-horno focus:ring-horno">
                    Gastos fijos vigentes
                </label>
                <label class="flex items-center gap-2 text-sm text-corteza">
                    <input type="checkbox" name="sections[]" value="monthly" checked
                        class="rounded border-gray-300 text-horno focus:ring-horno">
                    Histórico mensual comparativo
                </label>
                <label class="flex items-center gap-2 text-sm text-corteza">
                    <input type="checkbox" name="sections[]" value="details"
                        class="rounded border-gray-300 text-horno focus:ring-horno">
                    Detalle por gasto
                </label>
                <label class="flex items-center gap-2 text-sm text-corteza">
                    <input type="checkbox" name="sections[]" value="variable"
                        class="rounded border-gray-300 text-horno focus:ring-horno">
                    Gastos variables del período
                </label>
            </div>
        </div>

        <div>
            <label class="flex items-center gap-2 text-sm text-corteza">
                <input type="checkbox" x-model="useFilters"
                    class="rounded border-gray-300 text-horno focus:ring-horno">
                Aplicar los filtros de la pantalla ({{ implode(' · ', array_filter([
                    request('search') ? '«'.request('search').'»' : null,
                    request('status') === 'active' ? 'activos' : (request('status') === 'inactive' ? 'inactivos' : null),
                ])) ?: 'ninguno activo' }})
            </label>
            <input type="hidden" name="search" :value="useFilters ? '{{ addslashes(request('search', '')) }}' : ''">
            <input type="hidden" name="status" :value="useFilters ? '{{ request('status', '') }}' : ''">
        </div>

        <div class="flex gap-3 pt-2">
            <x-secondary-button type="submit" formtarget="_blank">Ver / Imprimir</x-secondary-button>
            <x-primary-button type="submit" formaction="{{ route('fixed-costs.report-pdf') }}">Descargar PDF</x-primary-button>
            <button type="button"
                x-on:click="$dispatch('close-modal', 'fixed-cost-report')"
                class="px-4 py-2 text-sm text-masa-madre hover:text-corteza">
                Cancelar
            </button>
        </div>
    </form>
</x-crud-modal>

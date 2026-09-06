<x-app-layout>
    <x-slot name="title">Historial de gastos fijos</x-slot>

    @php
        $prevPeriod = $period->copy()->subMonth()->format('Y-m');
        $nextPeriod = $period->copy()->addMonth()->format('Y-m');
        $periodLabel = \App\Services\FixedCostHistory::periodLabel($period);
    @endphp

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Historial de gastos fijos</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Monto de cada gasto fijo mes a mes.</p>
                </div>
                <a href="{{ route('fixed-costs.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">
                    ← Volver a Gastos fijos
                </a>
            </div>

            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('fixed-costs.history', ['period' => $prevPeriod]) }}"
                    class="p-2 rounded-md border border-gray-300 text-corteza hover:bg-miga transition-colors" aria-label="Mes anterior">
                    ‹
                </a>
                <form method="GET" action="{{ route('fixed-costs.history') }}">
                    <x-month-select name="period" :selected="$period->format('Y-m')"
                        class="border-gray-300 rounded-md shadow-sm text-sm font-semibold text-corteza focus:border-horno focus:ring-horno"
                        onchange="this.form.submit()" />
                </form>
                <a href="{{ route('fixed-costs.history', ['period' => $nextPeriod]) }}"
                    class="p-2 rounded-md border border-gray-300 text-corteza hover:bg-miga transition-colors" aria-label="Mes siguiente">
                    ›
                </a>
            </div>

            @if($isCurrentMonth)
                <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-md px-4 py-3">
                    Estás en el mes en curso: guardar acá actualiza el monto vigente de cada gasto y el overhead por hora.
                </div>
            @endif

            @if(session('status'))
                <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-md px-4 py-3">
                    {{ session('status') }}
                </div>
            @endif

            @if($rows->isEmpty())
                <x-empty-state>Todavía no hay gastos fijos cargados.</x-empty-state>
            @else
                @can('manage-costs')
                    <form method="POST" action="{{ route('fixed-costs.history.store') }}">
                        @csrf
                        <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
                @endcan

                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nombre</th>
                                <th class="px-4 py-3 font-medium">Categoría</th>
                                <th class="px-4 py-3 font-medium text-right">Monto de {{ $periodLabel }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($rows as $row)
                                @php $fixedCost = $row['fixedCost']; @endphp
                                <tr class="{{ $fixedCost->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 font-medium text-corteza">{{ $fixedCost->name }}</td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">{{ $fixedCost->category?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @can('manage-costs')
                                            <input type="number" step="0.01" min="0"
                                                name="amounts[{{ $fixedCost->id }}]"
                                                value="{{ $row['amount'] !== null ? number_format($row['amount'], 2, '.', '') : '' }}"
                                                placeholder="Sin cargar"
                                                class="w-32 text-right border-gray-300 rounded-md shadow-sm text-sm font-mono focus:border-horno focus:ring-horno {{ $row['carried'] ? 'text-masa-madre bg-miga/40' : 'text-corteza' }}">
                                        @else
                                            <span class="font-mono {{ $row['carried'] ? 'text-masa-madre' : 'text-corteza' }}">
                                                {{ $row['amount'] !== null ? '$ '.number_format($row['amount'], 2, ',', '.') : '—' }}
                                            </span>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-miga bg-miga/50">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-sm text-masa-madre">
                                    @if($carriedCount > 0)
                                        {{ $carriedCount }} {{ $carriedCount === 1 ? 'gasto sigue' : 'gastos siguen' }} arrastrando el monto de un mes anterior.
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-semibold text-corteza">
                                    Total: <span class="font-mono ml-1">$ {{ number_format($total, 2, ',', '.') }}</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @can('manage-costs')
                        <div class="flex justify-end">
                            <x-primary-button data-loading="Guardando…">Guardar {{ $periodLabel }}</x-primary-button>
                        </div>
                    </form>
                @endcan
            @endif

        </div>
    </div>
</x-app-layout>

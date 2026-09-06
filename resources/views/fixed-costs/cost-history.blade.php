<x-app-layout>
    <x-slot name="title">Historial — {{ $fixedCost->name }}</x-slot>

    @php
        $maxAmount = $timeline->max('amount') ?: 1;
    @endphp

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6 max-w-3xl">

            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="text-base font-semibold text-corteza">{{ $fixedCost->name }}</h2>
                    <p class="text-sm text-masa-madre mt-0.5">
                        {{ $fixedCost->category?->name ?? 'Sin categoría' }} ·
                        <x-status-badge :active="$fixedCost->active" />
                    </p>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('fixed-costs.history') }}" class="text-masa-madre hover:text-corteza hover:underline">Historial mensual</a>
                    <a href="{{ route('fixed-costs.index') }}" class="text-masa-madre hover:text-corteza hover:underline">← Volver a Gastos fijos</a>
                </div>
            </div>

            @if($timeline->isEmpty())
                <x-empty-state>Todavía no hay montos registrados para este gasto.</x-empty-state>
            @else
                <div class="bg-white rounded-lg shadow divide-y divide-miga">
                    @foreach($timeline as $point)
                        <div class="px-4 py-3 flex items-center gap-4">
                            <div class="w-28 shrink-0 text-sm text-corteza">
                                {{ \App\Services\FixedCostHistory::periodLabel($point['period']) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="h-2 rounded bg-miga overflow-hidden">
                                    <div class="h-full bg-horno" style="width: {{ max(2, round($point['amount'] / $maxAmount * 100)) }}%"></div>
                                </div>
                            </div>
                            <div class="w-28 shrink-0 text-right font-mono text-sm text-corteza">
                                $ {{ number_format($point['amount'], 2, ',', '.') }}
                            </div>
                            <div class="w-16 shrink-0 text-right text-xs font-medium
                                {{ $point['change_pct'] === null ? 'text-masa-madre' : ($point['change_pct'] > 0 ? 'text-red-600' : ($point['change_pct'] < 0 ? 'text-green-600' : 'text-masa-madre')) }}">
                                @if($point['change_pct'] !== null)
                                    {{ $point['change_pct'] > 0 ? '+' : '' }}{{ number_format($point['change_pct'], 1, ',', '.') }}%
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-masa-madre">{{ $timeline->count() }} mes(es) con monto propio registrado.</p>
            @endif

        </div>
    </div>
</x-app-layout>

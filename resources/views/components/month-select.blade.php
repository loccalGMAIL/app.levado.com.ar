{{--
    Select de meses en formato "YYYY-MM", con etiqueta en español ("Septiembre
    2026"). No se usa <input type="month">: el soporte de estilos es
    desparejo entre navegadores y el resto del proyecto usa selects nativos.

    Uso con Blade puro (create):
        <x-month-select id="create_fc_period" name="period" :selected="old('period', now()->format('Y-m'))" />

    Uso con Alpine (edit, x-model resuelve el valor seleccionado en el cliente):
        <x-month-select id="edit_fc_period" name="period" x-model="editing.period" />
--}}
@props(['selected' => null, 'monthsBack' => 24, 'monthsForward' => 1])

@php
    $current = \Illuminate\Support\Carbon::now()->startOfMonth();
    $options = collect(range($monthsForward, -$monthsBack))
        ->map(fn (int $offset) => $current->copy()->addMonths($offset))
        ->map(fn ($period) => ['value' => $period->format('Y-m'), 'label' => \App\Services\FixedCostHistory::periodLabel($period)]);
@endphp

<select {{ $attributes }}>
    @foreach($options as $option)
        <option value="{{ $option['value'] }}" @selected($selected === $option['value'])>{{ $option['label'] }}</option>
    @endforeach
</select>

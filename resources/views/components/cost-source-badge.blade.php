@props(['product', 'historyUrl' => null])
{{--
    De dónde sale el costo vigente del artículo. Sólo para REVENTA: en un
    elaborado el badge de tipo ya dice que el costo lo calcula la receta, y
    repetirlo sólo gasta espacio.

    Para la reventa sí aporta, porque son dos preguntas distintas: la REGLA
    (currentCostSource(), derivada del tipo) diría siempre 'compra', pero la
    PROCEDENCIA del valor vigente puede ser una factura o una carga a mano.
--}}
@if($product->isResale())
    @php
        $log = $product->latestCostLog;
        [$label, $title, $classes] = $log?->source === \App\Enums\CostLogSource::Manual
            ? ['Manual', 'Costo cargado a mano', 'bg-gray-100 text-gray-600']
            : ['Compra', $log !== null ? 'Costo imputado desde una factura' : 'El costo lo alimentan las compras', 'bg-sky-50 text-sky-700'];
        $base = "text-[10px] font-medium rounded px-1 py-0.5 {$classes}";
    @endphp

    @if($historyUrl)
        <button type="button"
            title="{{ $title }} · ver historial"
            @click.stop="openCostHistory('{{ $historyUrl }}', @js($product->name))"
            {{ $attributes->merge(['class' => $base.' hover:ring-1 hover:ring-current transition']) }}>{{ $label }}</button>
    @else
        <span title="{{ $title }}" {{ $attributes->merge(['class' => $base]) }}>{{ $label }}</span>
    @endif
@endif

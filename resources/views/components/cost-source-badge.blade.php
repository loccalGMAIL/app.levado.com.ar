@props(['product', 'historyUrl' => null])
{{--
    De dónde sale el costo vigente del artículo. Son dos preguntas distintas:
    la REGLA (currentCostSource(), derivada del tipo) y la PROCEDENCIA del último
    valor (el log de costo). Para un elaborado sólo aplica la regla; para uno de
    reventa manda la procedencia, porque el costo puede haberse tipeado a mano.
--}}
@php
    $log = $product->isResale() ? $product->latestCostLog : null;
    [$label, $title, $classes] = match (true) {
        $product->isManufactured() => ['Receta', 'El costo lo calcula la receta', 'bg-amber-50 text-amber-700'],
        $log?->source === \App\Enums\CostLogSource::Manual => ['Manual', 'Costo cargado a mano', 'bg-gray-100 text-gray-600'],
        $log !== null => ['Compra', 'Costo imputado desde una factura', 'bg-sky-50 text-sky-700'],
        default => ['Compra', 'El costo lo alimentan las compras', 'bg-sky-50 text-sky-700'],
    };
    $base = "text-[10px] font-medium rounded px-1 py-0.5 {$classes}";
@endphp

@if($historyUrl && $product->isResale())
    <button type="button"
        title="{{ $title }} · ver historial"
        @click.stop="openCostHistory('{{ $historyUrl }}', @js($product->name))"
        {{ $attributes->merge(['class' => $base.' hover:ring-1 hover:ring-current transition']) }}>{{ $label }}</button>
@else
    <span title="{{ $title }}" {{ $attributes->merge(['class' => $base]) }}>{{ $label }}</span>
@endif

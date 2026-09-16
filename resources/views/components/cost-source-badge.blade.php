@props(['product', 'historyUrl' => null])
{{--
    De dónde sale el costo vigente del artículo. Para REVENTA son dos
    preguntas distintas: la REGLA (currentCostSource(), derivada del tipo)
    diría siempre 'compra', pero la PROCEDENCIA del valor vigente puede ser
    una factura o una carga a mano — por eso mira latestCostLog.

    Para un ELABORADO el costo vigente SIGUE saliendo de la receta (no de
    productions ni de ningún log): la etiqueta acá es la regla nomás, nunca
    la procedencia. Solo se muestra si hay link: sin historyUrl el badge de
    tipo ya dice "Elaborado" y repetirlo no aporta nada (por eso /stock,
    que no tiene el modal de historial, sigue sin badge para un elaborado).
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
@elseif($historyUrl)
    <button type="button"
        title="El costo lo calcula la receta · ver historial de fabricaciones"
        @click.stop="openCostHistory('{{ $historyUrl }}', @js($product->name))"
        {{ $attributes->merge(['class' => 'text-[10px] font-medium rounded px-1 py-0.5 bg-amber-50 text-amber-700 hover:ring-1 hover:ring-current transition']) }}>Receta</button>
@endif

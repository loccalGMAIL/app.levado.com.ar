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
            title="{{ $title }} · ver historial" aria-label="{{ $title }} · ver historial"
            @click.stop="openCostHistory('{{ $historyUrl }}', @js($product->name))"
            {{ $attributes->merge(['class' => 'p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors']) }}>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>
    @else
        <span title="{{ $title }}" {{ $attributes->merge(['class' => $base]) }}>{{ $label }}</span>
    @endif
@elseif($historyUrl)
    @php $title = 'El costo lo calcula la receta · ver historial de fabricaciones'; @endphp
    <button type="button"
        title="{{ $title }}" aria-label="{{ $title }}"
        @click.stop="openCostHistory('{{ $historyUrl }}', @js($product->name))"
        {{ $attributes->merge(['class' => 'p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors']) }}>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </button>
@endif

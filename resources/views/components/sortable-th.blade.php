{{--
    Encabezado de columna ordenable. Reemplaza el bloque $sortUrl/$sortIcon
    que estaba duplicado en las vistas de índice: arma la URL preservando los
    filtros (menos la página), alterna asc/desc al re-clickear la columna y
    muestra la flecha cuando la columna está activa.

    Uso: <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
    $sort y $dir son los vigentes de la vista (con su default propio aplicado).

    sort-param / dir-param: nombres de los parámetros de la URL, para cuando una
    misma pantalla tiene dos tablas ordenables (ver team/index).
    compact: padding de las tablas chicas dentro de una card (sin px).
--}}
@props(['column', 'sort', 'dir' => 'asc', 'align' => 'left', 'sortParam' => 'sort', 'dirParam' => 'dir', 'compact' => false])

@php
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
    $url = request()->url().'?'.http_build_query(array_merge(
        request()->except([$sortParam, $dirParam, 'page']),
        [$sortParam => $column, $dirParam => $nextDir],
    ));
    $icon = $sort === $column ? ($dir === 'asc' ? '↑' : '↓') : '';
@endphp

<th {{ $attributes->merge(['class' => ($compact ? 'pb-2' : 'px-4 py-3 font-medium').($align === 'right' ? ' text-right' : '')]) }}>
    <a href="{{ $url }}" class="hover:text-corteza inline-flex items-center gap-1 {{ $align === 'right' ? 'justify-end' : '' }}">
        {{ $slot }} <span class="text-xs">{{ $icon }}</span>
    </a>
</th>

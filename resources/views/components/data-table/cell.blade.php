{{--
    Celda de un listado. `role` es lo que el CSS usa para ubicarla dentro de la
    card; en la tabla es una celda más.

    role:   title | subtitle | figure | badge | meta | actions
    cards:  show (default) | hide  → columna que no entra en la card
    label:  prefijo que muestra la card ("Stock:"); la tabla ya tiene su <th>
--}}
@props(['role' => 'meta', 'align' => 'left', 'cards' => 'show', 'label' => null])

@php
    // El slot ya viene renderizado. Se recorta para poder distinguir una celda
    // sin valor —la tabla muestra «—», la card la omite— sin depender de :empty,
    // que el whitespace de Blade vuelve inservible.
    $content = trim($slot->toHtml());
    $isEmpty = $content === '';
    $showsDash = $isEmpty && in_array($role, ['subtitle', 'figure', 'meta'], true);
@endphp

<td {{ $attributes->class(['px-4 py-3', 'text-right' => $align === 'right']) }}
    data-role="{{ $role }}"
    @if($cards === 'hide') data-cards="hide" @endif
    @if($isEmpty) data-empty @endif
    @if($label) data-label="{{ $label }}" @endif
>{!! $showsDash ? '—' : $content !!}</td>

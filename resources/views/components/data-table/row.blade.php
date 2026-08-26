@props(['dimmed' => false])

<tr {{ $attributes->class(['opacity-50' => $dimmed]) }}>{{ $slot }}</tr>

@props(['active'])

@php
$classes = ($active ?? false)
            ? 'self-stretch inline-flex items-center px-3 border-b-2 border-horno text-sm font-medium text-corteza focus:outline-none transition duration-150 ease-in-out'
            : 'self-stretch inline-flex items-center px-3 border-b-2 border-transparent text-sm font-medium text-masa-madre hover:text-corteza hover:border-miga focus:outline-none transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

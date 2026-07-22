@props(['status'])

@php
    $isConfirmed = $status === \App\Enums\ProductionStatus::Confirmed;
    $classes = $isConfirmed
        ? 'bg-green-100 text-green-700'
        : 'bg-gray-100 text-gray-500';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {$classes}"]) }}>
    {{ $status->label() }}
</span>

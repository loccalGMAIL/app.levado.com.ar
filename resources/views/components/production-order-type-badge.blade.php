@props(['type'])

@php
    $classes = match ($type) {
        \App\Enums\ProductionOrderType::Spontaneous => 'bg-horno/10 text-horno',
        \App\Enums\ProductionOrderType::Instant => 'bg-amber-100 text-amber-800',
        default => 'bg-miga text-masa-madre',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {$classes}"]) }}>
    {{ $type->label() }}
</span>

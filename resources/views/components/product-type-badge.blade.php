@props(['type'])
@php
    $classes = match ($type) {
        \App\Enums\ProductType::Manufactured => 'bg-amber-100 text-amber-700',
        \App\Enums\ProductType::Combo => 'bg-green-100 text-green-700',
        default => 'bg-sky-100 text-sky-700',
    };
@endphp
<span class="text-[10px] font-medium rounded px-1.5 py-0.5 {{ $classes }}">{{ $type->label() }}</span>

@props(['type'])
@php
    $isManufactured = $type === \App\Enums\ProductType::Manufactured;
    $classes = $isManufactured ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700';
@endphp
<span class="text-[10px] font-medium rounded px-1.5 py-0.5 {{ $classes }}">{{ $type->label() }}</span>

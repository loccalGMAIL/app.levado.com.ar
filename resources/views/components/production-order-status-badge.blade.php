@props(['status'])

@php
    $classes = match ($status) {
        \App\Enums\ProductionOrderStatus::Draft => 'bg-gray-100 text-gray-500',
        \App\Enums\ProductionOrderStatus::Confirmed => 'bg-blue-100 text-blue-700',
        \App\Enums\ProductionOrderStatus::InProduction => 'bg-amber-100 text-amber-700',
        \App\Enums\ProductionOrderStatus::Done => 'bg-green-100 text-green-700',
        \App\Enums\ProductionOrderStatus::Cancelled => 'bg-red-100 text-red-600',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {$classes}"]) }}>
    {{ $status->label() }}
</span>

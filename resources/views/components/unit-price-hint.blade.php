@props(['compact' => false])

{{-- Requiere estar dentro de un scope Alpine de window.purchaseLineUnits(). --}}
<template x-if="priceConversion">
    <div {{ $attributes->merge(['class' => 'mt-1 rounded-md bg-amber-50 border border-amber-200 px-2 py-1.5 text-amber-800 ' . ($compact ? 'text-[11px]' : 'text-xs')]) }}>
        <span>
            ¿El precio era por <span class="font-medium" x-text="priceConversion.from"></span>?
        </span>
        <div class="flex items-center gap-2 mt-1">
            <button type="button" @click="applyPriceConversion()"
                class="font-medium underline underline-offset-2 hover:text-amber-900 text-left">
                Convertir a $<span x-text="fmtPrice(priceConversion.price)"></span> por <span x-text="priceConversion.to"></span>
            </button>
            <button type="button" @click="dismissPriceConversion()"
                class="text-amber-600 hover:text-amber-800 shrink-0">
                Dejar como está
            </button>
        </div>
    </div>
</template>

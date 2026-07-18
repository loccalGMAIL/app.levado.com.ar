{{--
    Envoltorio del patrón cards-en-mobile / tabla-en-desktop con el toggle
    "Ver tabla completa ↓" / "← Volver a cards". Requiere que el x-data del
    contenedor padre declare `mobileExpanded: false` (convención existente
    en todas las vistas de índice).

    Uso:
    <x-responsive-table>
        <x-slot:cards> ...una card por registro... </x-slot:cards>
        <thead>...</thead>
        <tbody>...</tbody>
    </x-responsive-table>
--}}
@props([])

{{-- Cards (mobile) --}}
<div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="space-y-3">
    {{ $cards }}
    <button type="button" @click="mobileExpanded = true"
        class="w-full py-2 text-sm text-masa-madre hover:text-corteza text-center">
        Ver tabla completa ↓
    </button>
</div>

{{-- Tabla (desktop siempre, mobile si está expandida) --}}
<div :class="mobileExpanded ? '' : 'hidden md:block'" class="bg-white rounded-lg shadow overflow-x-auto">
    <div class="md:hidden px-4 py-2 border-b border-miga">
        <button type="button" @click="mobileExpanded = false"
            class="text-sm text-masa-madre hover:text-corteza">
            ← Volver a cards
        </button>
    </div>
    <table class="w-full text-sm text-left">
        {{ $slot }}
    </table>

    {{-- Paginación u otro pie dentro del wrapper de la tabla --}}
    @isset($footer)
        {{ $footer }}
    @endisset
</div>

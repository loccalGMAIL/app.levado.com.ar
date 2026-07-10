@props(['title', 'slug', 'active' => false])

{{-- Grupo colapsable: recuerda su estado en localStorage; si contiene la ruta
     activa se fuerza abierto para que el ítem actual siempre quede visible. --}}
<div x-data="{
        open: {{ $active ? 'true' : "JSON.parse(localStorage.getItem('sidebar-group-{$slug}') ?? 'true')" }},
        toggle() {
            this.open = ! this.open;
            localStorage.setItem('sidebar-group-{{ $slug }}', JSON.stringify(this.open));
        }
    }">
    <button type="button" @click="toggle()"
        class="w-full flex items-center justify-between px-5 pt-4 pb-1 text-[9.5px] font-semibold uppercase tracking-widest text-harina/35 hover:text-harina/60 transition-colors">
        {{ $title }}
        <svg class="w-2.5 h-2.5 shrink-0 transition-transform" :class="open ? '' : '-rotate-90'"
            fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
    </button>
    <div x-show="open" x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0">
        {{ $slot }}
    </div>
</div>

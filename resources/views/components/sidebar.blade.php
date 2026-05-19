@php
    $inNegocio = request()->routeIs('business.*', 'team.*', 'locations.*');
    $inCostos  = request()->routeIs('ingredients.*', 'suppliers.*', 'packaging.*', 'fixed-costs.*');
@endphp

<aside class="hidden sm:flex flex-col w-52 shrink-0 bg-white border-r border-miga min-h-full">

    <nav class="flex-1 px-2 py-4 space-y-0.5">

        {{-- Inicio --}}
        <a href="{{ route('dashboard') }}"
            class="flex items-center gap-2.5 px-3 py-2 text-sm rounded-md transition-colors
                {{ request()->routeIs('dashboard')
                    ? 'bg-miga text-corteza font-medium'
                    : 'text-masa-madre hover:text-corteza hover:bg-harina' }}">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            Inicio
        </a>

        {{-- Costos --}}
        @if($inCostos)
            <div class="pt-3 pb-1 px-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-masa-madre">Costos</span>
            </div>

            <a href="{{ route('ingredients.index') }}"
                class="flex items-center gap-2.5 px-3 py-2 text-sm rounded-md transition-colors
                    {{ request()->routeIs('ingredients.*')
                        ? 'bg-miga text-corteza font-medium'
                        : 'text-masa-madre hover:text-corteza hover:bg-harina' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                Ingredientes
            </a>

            <a href="{{ route('suppliers.index') }}"
                class="flex items-center gap-2.5 px-3 py-2 text-sm rounded-md transition-colors
                    {{ request()->routeIs('suppliers.*')
                        ? 'bg-miga text-corteza font-medium'
                        : 'text-masa-madre hover:text-corteza hover:bg-harina' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 18H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v3M8 10h8M8 14h4m4 4v-4m0 4h-4m4 0l-3-3" />
                </svg>
                Proveedores
            </a>

            <a href="{{ route('packaging.index') }}"
                class="flex items-center gap-2.5 px-3 py-2 text-sm rounded-md transition-colors
                    {{ request()->routeIs('packaging.*')
                        ? 'bg-miga text-corteza font-medium'
                        : 'text-masa-madre hover:text-corteza hover:bg-harina' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4M12 3v18" />
                </svg>
                Envases
            </a>

            <a href="{{ route('fixed-costs.index') }}"
                class="flex items-center gap-2.5 px-3 py-2 text-sm rounded-md transition-colors
                    {{ request()->routeIs('fixed-costs.*')
                        ? 'bg-miga text-corteza font-medium'
                        : 'text-masa-madre hover:text-corteza hover:bg-harina' }}">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                Gastos Fijos
            </a>
        @endif

        {{-- Mi negocio --}}
        @if($inNegocio)
            <div class="pt-3 pb-1 px-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-masa-madre">Mi negocio</span>
            </div>

            @can('edit-settings')
                <a href="{{ route('business.edit') }}"
                    class="flex items-center gap-2.5 px-3 py-2 text-sm rounded-md transition-colors
                        {{ request()->routeIs('business.*')
                            ? 'bg-miga text-corteza font-medium'
                            : 'text-masa-madre hover:text-corteza hover:bg-harina' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    General
                </a>
            @endcan

            @can('manage-team')
                <a href="{{ route('team.index') }}"
                    class="flex items-center gap-2.5 px-3 py-2 text-sm rounded-md transition-colors
                        {{ request()->routeIs('team.*')
                            ? 'bg-miga text-corteza font-medium'
                            : 'text-masa-madre hover:text-corteza hover:bg-harina' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Personal
                </a>
            @endcan

            @can('edit-settings')
                <a href="{{ route('locations.index') }}"
                    class="flex items-center gap-2.5 px-3 py-2 text-sm rounded-md transition-colors
                        {{ request()->routeIs('locations.*')
                            ? 'bg-miga text-corteza font-medium'
                            : 'text-masa-madre hover:text-corteza hover:bg-harina' }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Sucursales
                </a>
            @endcan
        @endif

    </nav>

</aside>

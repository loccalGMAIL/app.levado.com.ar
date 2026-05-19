@php
    try {
        $sidebarTenant = app(\App\Models\Tenant::class);
    } catch (\Throwable) {
        $sidebarTenant = null;
    }

    $navItem = fn(string $route, string $label, string $icon, string $pattern) =>
        ['route' => $route, 'label' => $label, 'icon' => $icon, 'active' => request()->routeIs($pattern)];
@endphp

<aside class="hidden sm:flex flex-col w-52 shrink-0 bg-masa-madre min-h-full">

    {{-- Tenant info --}}
    @auth
    <div class="px-5 py-4 border-b border-white/10">
        <div class="text-[11px] font-semibold uppercase tracking-widest text-horno truncate">
            {{ $sidebarTenant?->name ?? config('app.name') }}
        </div>
        <div class="text-[11px] text-harina/50 mt-0.5 truncate">
            {{ Auth::user()->name }}
        </div>
    </div>
    @endauth

    {{-- Navigation --}}
    <nav class="flex-1 py-3 overflow-y-auto">

        {{-- Principal --}}
        <div class="px-5 pt-2 pb-1 text-[9.5px] font-semibold uppercase tracking-widest text-harina/35">
            Principal
        </div>

        @include('components.sidebar-item', [
            'href'   => route('dashboard'),
            'label'  => 'Dashboard',
            'active' => request()->routeIs('dashboard'),
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />',
        ])

        {{-- Costos --}}
        <div class="px-5 pt-4 pb-1 text-[9.5px] font-semibold uppercase tracking-widest text-harina/35">
            Costos
        </div>

        @include('components.sidebar-item', [
            'href'   => route('recipes.index'),
            'label'  => 'Recetas',
            'active' => request()->routeIs('recipes.*'),
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />',
        ])

        @include('components.sidebar-item', [
            'href'   => route('ingredients.index'),
            'label'  => 'Ingredientes',
            'active' => request()->routeIs('ingredients.*'),
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />',
        ])

        @include('components.sidebar-item', [
            'href'   => route('suppliers.index'),
            'label'  => 'Proveedores',
            'active' => request()->routeIs('suppliers.*'),
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 18H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v3M8 10h8M8 14h4m4 4v-4m0 4h-4m4 0l-3-3" />',
        ])

        @include('components.sidebar-item', [
            'href'   => route('packaging.index'),
            'label'  => 'Envases',
            'active' => request()->routeIs('packaging.*'),
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4M12 3v18" />',
        ])

        @include('components.sidebar-item', [
            'href'   => route('fixed-costs.index'),
            'label'  => 'Gastos Fijos',
            'active' => request()->routeIs('fixed-costs.*'),
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />',
        ])

        @include('components.sidebar-item', [
            'href'   => route('labor-types.index'),
            'label'  => 'Mano de Obra',
            'active' => request()->routeIs('labor-types.*'),
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />',
        ])

        {{-- Negocio --}}
        @canany(['edit-settings', 'manage-team'])
        <div class="px-5 pt-4 pb-1 text-[9.5px] font-semibold uppercase tracking-widest text-harina/35">
            Negocio
        </div>

        @can('edit-settings')
            @include('components.sidebar-item', [
                'href'   => route('business.edit'),
                'label'  => 'Mi negocio',
                'active' => request()->routeIs('business.*'),
                'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
            ])
        @endcan

        @can('manage-team')
            @include('components.sidebar-item', [
                'href'   => route('team.index'),
                'label'  => 'Mi equipo',
                'active' => request()->routeIs('team.*'),
                'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />',
            ])
        @endcan

        @can('edit-settings')
            @include('components.sidebar-item', [
                'href'   => route('locations.index'),
                'label'  => 'Sucursales',
                'active' => request()->routeIs('locations.*'),
                'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />',
            ])
        @endcan
        @endcanany

        {{-- Backoffice (super admin) --}}
        @auth
        @if(Auth::user()->isSuperAdmin())
        <div class="px-5 pt-4 pb-1 text-[9.5px] font-semibold uppercase tracking-widest text-harina/35">
            Sistema
        </div>
        @include('components.sidebar-item', [
            'href'   => route('admin.dashboard'),
            'label'  => 'Backoffice',
            'active' => request()->routeIs('admin.*'),
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />',
        ])
        @endif
        @endauth

    </nav>

    {{-- Footer --}}
    <div class="px-5 py-3 border-t border-white/10 text-[11px] text-harina/40">
        v{{ config('app.version') }}
    </div>

</aside>

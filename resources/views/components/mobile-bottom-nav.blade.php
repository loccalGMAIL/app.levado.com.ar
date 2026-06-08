<div x-data="{ open: false }" class="sm:hidden">

    {{-- Overlay --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="open = false"
        class="fixed inset-0 z-40 bg-corteza/60"
        style="display: none;"
    ></div>

    {{-- Drawer "Más" --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        class="fixed bottom-16 inset-x-0 z-50 bg-white rounded-t-2xl shadow-2xl border-t border-miga max-h-[70vh] overflow-y-auto"
        style="display: none;"
    >
        <div class="flex justify-center pt-3 pb-1">
            <div class="w-10 h-1 bg-miga rounded-full"></div>
        </div>

        <div class="px-4 py-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-corteza/40 mb-2 px-1">Costos</p>

            <a href="{{ route('fixed-costs.index') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('fixed-costs.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                Gastos Fijos
            </a>

            <a href="{{ route('packaging.index') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('packaging.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4M12 3v18" />
                </svg>
                Envases
            </a>

            <a href="{{ route('labor-types.index') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('labor-types.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Mano de Obra
            </a>

            <a href="{{ route('suppliers.index') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('suppliers.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 18H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v3M8 10h8M8 14h4m4 4v-4m0 4h-4m4 0l-3-3" />
                </svg>
                Proveedores
            </a>
        </div>

        @canany(['edit-settings', 'manage-team'])
        <div class="px-4 pb-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-corteza/40 mb-2 px-1">Negocio</p>

            @can('edit-settings')
            <a href="{{ route('business.edit') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('business.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Mi negocio
            </a>
            @endcan

            @can('manage-team')
            <a href="{{ route('team.index') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('team.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Mi equipo
            </a>
            @endcan

            @can('edit-settings')
            <a href="{{ route('locations.index') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('locations.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Sucursales
            </a>
            @endcan
        </div>
        @endcanany

        @auth
        @if(Auth::user()->isSuperAdmin())
        <div class="px-4 pb-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-corteza/40 mb-2 px-1">Sistema</p>
            <a href="{{ route('admin.dashboard') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('admin.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
                Backoffice
            </a>
        </div>
        @endif
        @endauth

        <div class="px-4 pb-4 border-t border-miga pt-3">
            <a href="{{ route('profile.edit') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('profile.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                Mi perfil
                <span class="ml-auto text-xs text-corteza/40">{{ Auth::user()->name }}</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" @click="open = false"
                    class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium text-corteza hover:bg-miga transition-colors">
                    <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>

    {{-- Barra inferior fija --}}
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-masa-madre border-t border-white/10 flex items-stretch h-16">

        {{-- Inicio --}}
        <a href="{{ route('dashboard') }}"
            class="flex-1 flex flex-col items-center justify-center gap-1 transition-colors
                {{ request()->routeIs('dashboard') ? 'text-horno' : 'text-harina/55 hover:text-harina' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span class="text-[10px] font-semibold leading-none">Inicio</span>
        </a>

        {{-- Recetas --}}
        <a href="{{ route('recipes.index') }}"
            class="flex-1 flex flex-col items-center justify-center gap-1 transition-colors
                {{ request()->routeIs('recipes.*') ? 'text-horno' : 'text-harina/55 hover:text-harina' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <span class="text-[10px] font-semibold leading-none">Recetas</span>
        </a>

        {{-- Ingredientes --}}
        <a href="{{ route('ingredients.index') }}"
            class="flex-1 flex flex-col items-center justify-center gap-1 transition-colors
                {{ request()->routeIs('ingredients.*') ? 'text-horno' : 'text-harina/55 hover:text-harina' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span class="text-[10px] font-semibold leading-none">Ingredientes</span>
        </a>

        {{-- Compras --}}
        <a href="{{ route('purchases.index') }}"
            class="flex-1 flex flex-col items-center justify-center gap-1 transition-colors
                {{ request()->routeIs('purchases.*') ? 'text-horno' : 'text-harina/55 hover:text-harina' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span class="text-[10px] font-semibold leading-none">Compras</span>
        </a>

        {{-- Más --}}
        <button @click="open = !open"
            class="flex-1 flex flex-col items-center justify-center gap-1 transition-colors
                {{ request()->routeIs(['fixed-costs.*', 'packaging.*', 'labor-types.*', 'suppliers.*', 'business.*', 'team.*', 'locations.*', 'profile.*', 'admin.*']) ? 'text-horno' : 'text-harina/55 hover:text-harina' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span class="text-[10px] font-semibold leading-none">Más</span>
        </button>

    </nav>
</div>

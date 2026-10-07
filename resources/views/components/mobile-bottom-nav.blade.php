<div x-data="{ open: false }" class="print:hidden sm:hidden">

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

        @foreach($drawerGroups as $groupTitle => $items)
        <div class="px-4 {{ $loop->first ? 'py-3' : 'pb-3' }}">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-corteza/40 mb-2 px-1">{{ $groupTitle }}</p>

            @foreach($items as $item)
            <a href="{{ route($item->routeName()) }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs(...$item->activePatterns()) ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    {!! $item->iconPath() !!}
                </svg>
                {{ $item->label() }}
            </a>
            @endforeach
        </div>
        @endforeach

        @canany(['edit-settings', 'manage-team'])
        <div class="px-4 pb-3">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-corteza/40 mb-2 px-1">Administración</p>

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

            @can('edit-settings')
            <a href="{{ route('alerts.edit') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('alerts.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                Alertas
            </a>
            @endcan

            @can('edit-settings')
            <a href="{{ route('mobile-shortcuts.edit') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('mobile-shortcuts.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                Accesos rápidos
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

            @can('edit-settings')
            <a href="{{ route('price-lists.index') }}" @click="open = false"
                class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-medium transition-colors
                    {{ request()->routeIs('price-lists.*') ? 'bg-horno/10 text-horno' : 'text-corteza hover:bg-miga' }}">
                <svg class="w-5 h-5 shrink-0 opacity-70" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                Listas de precios
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

        {{-- Accesos rápidos configurables por tenant --}}
        @foreach($barShortcuts as $shortcut)
        <a href="{{ route($shortcut->routeName()) }}"
            class="flex-1 flex flex-col items-center justify-center gap-1 transition-colors
                {{ request()->routeIs(...$shortcut->activePatterns()) ? 'text-horno' : 'text-harina/55 hover:text-harina' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                {!! $shortcut->iconPath() !!}
            </svg>
            <span class="text-[10px] font-semibold leading-none">{{ $shortcut->shortLabel() }}</span>
        </a>
        @endforeach

        {{-- Más --}}
        <button @click="open = !open"
            class="flex-1 flex flex-col items-center justify-center gap-1 transition-colors
                {{ request()->routeIs($moreActivePatterns) ? 'text-horno' : 'text-harina/55 hover:text-harina' }}">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span class="text-[10px] font-semibold leading-none">Más</span>
        </button>

    </nav>
</div>

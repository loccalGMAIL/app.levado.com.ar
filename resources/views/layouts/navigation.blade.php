<nav x-data="{ open: false }" class="bg-white border-b border-miga">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14">

            {{-- Logo + links primarios --}}
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="text-corteza hover:text-horno transition-colors">
                        <x-application-logo class="h-7 w-auto" />
                    </a>
                </div>

                <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Inicio
                    </x-nav-link>

                    @can('manage-team')
                        <x-nav-link :href="route('team.index')" :active="request()->routeIs('team.*')">
                            Mi equipo
                        </x-nav-link>
                    @endcan

                    @can('edit-settings')
                        <x-nav-link :href="route('business.edit')" :active="request()->routeIs('business.*')">
                            Mi negocio
                        </x-nav-link>
                    @endcan
                </div>
            </div>

            {{-- Dropdown usuario --}}
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-masa-madre hover:text-corteza hover:bg-harina focus:outline-none transition duration-150">
                            {{ Auth::user()->name }}
                            <svg class="ms-1 h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            Mi perfil
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                Cerrar sesión
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            {{-- Hamburger --}}
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-masa-madre hover:bg-miga focus:outline-none transition duration-150">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Menú responsive --}}
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-miga">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                Inicio
            </x-responsive-nav-link>

            @can('manage-team')
                <x-responsive-nav-link :href="route('team.index')" :active="request()->routeIs('team.*')">
                    Mi equipo
                </x-responsive-nav-link>
            @endcan

            @can('edit-settings')
                <x-responsive-nav-link :href="route('business.edit')" :active="request()->routeIs('business.*')">
                    Mi negocio
                </x-responsive-nav-link>
            @endcan
        </div>

        <div class="pt-4 pb-1 border-t border-miga">
            <div class="px-4">
                <div class="font-medium text-base text-corteza">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-masa-madre">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    Mi perfil
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        Cerrar sesión
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

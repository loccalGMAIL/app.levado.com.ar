@php
    try {
        $navTenant = app(\App\Models\Tenant::class);
    } catch (\Throwable) {
        $navTenant = null;
    }
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-miga">
    <div class="flex h-16">

        {{-- Bloque de marca — mismo ancho que el sidebar, fondo oscuro para continuidad visual --}}
        <a href="{{ route('dashboard') }}"
            class="hidden sm:flex w-52 shrink-0 bg-masa-madre border-r border-white/10 items-center justify-center p-2
                   hover:bg-masa-madre/90 transition-colors">
            @if($navTenant?->logo_path)
                <img src="{{ Storage::url($navTenant->logo_path) }}"
                     alt="{{ $navTenant->name }}"
                     class="w-full h-full object-contain">
            @else
                <x-application-logo class="w-full h-full text-harina" />
            @endif
        </a>

        {{-- Logo móvil --}}
        <div class="flex items-center px-4 sm:hidden">
            <a href="{{ route('dashboard') }}" class="font-serif text-xl text-corteza">
                levado
            </a>
        </div>

        {{-- Breadcrumbs (desktop) --}}
        <div class="hidden flex-1 sm:flex sm:items-center sm:px-6 gap-1.5 text-sm overflow-hidden">
            @auth
            @php
                $crumbs = [];

                if (request()->routeIs('dashboard')) {
                    // no extra crumbs
                } elseif (request()->routeIs('recipes.show')) {
                    $crumbs[] = ['label' => 'Recetas', 'href' => route('recipes.index')];
                    $recipe = request()->route('recipe');
                    if ($recipe) { $crumbs[] = ['label' => $recipe->name, 'href' => null]; }
                } elseif (request()->routeIs('recipes.*')) {
                    $crumbs[] = ['label' => 'Recetas', 'href' => null];
                } elseif (request()->routeIs('ingredients.*')) {
                    $crumbs[] = ['label' => 'Costos', 'href' => null];
                    $crumbs[] = ['label' => 'Ingredientes', 'href' => null];
                } elseif (request()->routeIs('suppliers.*')) {
                    $crumbs[] = ['label' => 'Costos', 'href' => null];
                    $crumbs[] = ['label' => 'Proveedores', 'href' => null];
                } elseif (request()->routeIs('packaging.*')) {
                    $crumbs[] = ['label' => 'Costos', 'href' => null];
                    $crumbs[] = ['label' => 'Envases', 'href' => null];
                } elseif (request()->routeIs('fixed-costs.*')) {
                    $crumbs[] = ['label' => 'Costos', 'href' => null];
                    $crumbs[] = ['label' => 'Gastos Fijos', 'href' => null];
                } elseif (request()->routeIs('labor-types.*')) {
                    $crumbs[] = ['label' => 'Costos', 'href' => null];
                    $crumbs[] = ['label' => 'Mano de Obra', 'href' => null];
                } elseif (request()->routeIs('business.*')) {
                    $crumbs[] = ['label' => 'Negocio', 'href' => null];
                    $crumbs[] = ['label' => 'Mi negocio', 'href' => null];
                } elseif (request()->routeIs('team.*')) {
                    $crumbs[] = ['label' => 'Negocio', 'href' => null];
                    $crumbs[] = ['label' => 'Mi equipo', 'href' => null];
                } elseif (request()->routeIs('locations.*')) {
                    $crumbs[] = ['label' => 'Negocio', 'href' => null];
                    $crumbs[] = ['label' => 'Sucursales', 'href' => null];
                } elseif (request()->routeIs('profile.*')) {
                    $crumbs[] = ['label' => 'Mi perfil', 'href' => null];
                } elseif (request()->routeIs('admin.*')) {
                    $crumbs[] = ['label' => 'Sistema', 'href' => null];
                    $crumbs[] = ['label' => 'Backoffice', 'href' => null];
                }
            @endphp

            <a href="{{ route('dashboard') }}" class="font-medium text-corteza truncate shrink-0 hover:text-masa-madre transition-colors">
                {{ $navTenant?->name ?? config('app.name') }}
            </a>

            @foreach($crumbs as $crumb)
                <span class="text-corteza/30 shrink-0">›</span>
                @if($crumb['href'])
                    <a href="{{ $crumb['href'] }}" class="text-masa-madre hover:text-corteza transition-colors truncate shrink-0">{{ $crumb['label'] }}</a>
                @else
                    <span class="text-corteza/70 truncate {{ $loop->last ? '' : 'shrink-0' }}">{{ $crumb['label'] }}</span>
                @endif
            @endforeach
            @endauth
        </div>

        {{-- Usuario (desktop) --}}
        <div class="hidden sm:flex sm:items-center sm:px-4">
            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-masa-madre hover:text-corteza hover:bg-miga focus:outline-none transition duration-150">
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

        {{-- Hamburger (móvil) --}}
        <div class="flex-1 flex items-center justify-end px-4 sm:hidden">
            <button @click="open = ! open"
                class="inline-flex items-center justify-center p-2 rounded-md text-masa-madre hover:bg-miga focus:outline-none transition duration-150">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': ! open}" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open, 'inline-flex': open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

    </div>

    {{-- Menú responsive (móvil) --}}
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-miga">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                Inicio
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('recipes.index')" :active="request()->routeIs('recipes.*')">
                Recetas
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('ingredients.index')" :active="request()->routeIs('ingredients.*')">
                Costos — Ingredientes
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')">
                Costos — Proveedores
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('packaging.index')" :active="request()->routeIs('packaging.*')">
                Costos — Envases
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('fixed-costs.index')" :active="request()->routeIs('fixed-costs.*')">
                Costos — Gastos Fijos
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('labor-types.index')" :active="request()->routeIs('labor-types.*')">
                Costos — Mano de Obra
            </x-responsive-nav-link>

            @can('edit-settings')
                <x-responsive-nav-link :href="route('business.edit')" :active="request()->routeIs('business.*')">
                    Negocio — General
                </x-responsive-nav-link>
            @endcan

            @can('manage-team')
                <x-responsive-nav-link :href="route('team.index')" :active="request()->routeIs('team.*')">
                    Negocio — Mi equipo
                </x-responsive-nav-link>
            @endcan

            @auth
            @if(Auth::user()->isSuperAdmin())
                <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.*')">
                    Backoffice
                </x-responsive-nav-link>
            @endif
            @endauth
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

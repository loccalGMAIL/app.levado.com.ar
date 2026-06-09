@php
    try {
        $navTenant = app(\App\Models\Tenant::class);
    } catch (\Throwable) {
        $navTenant = null;
    }
@endphp

<nav class="bg-white border-b border-miga">
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
                } elseif (request()->routeIs('purchases.show')) {
                    $crumbs[] = ['label' => 'Compras', 'href' => route('purchases.index')];
                    $purchase = request()->route('purchase');
                    $label = $purchase?->invoice_number ? "Factura #{$purchase->invoice_number}" : 'Compra #' . $purchase?->id;
                    $crumbs[] = ['label' => $label, 'href' => null];
                } elseif (request()->routeIs('purchases.*')) {
                    $crumbs[] = ['label' => 'Costos', 'href' => null];
                    $crumbs[] = ['label' => 'Compras', 'href' => null];
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

    </div>
</nav>

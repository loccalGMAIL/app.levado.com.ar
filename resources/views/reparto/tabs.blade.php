{{-- Pestañas de Reparto: clientes y repartidores. --}}
<div class="flex items-center gap-1 border-b border-miga">
    <a href="{{ route('reparto.clientes.index') }}"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ request()->routeIs('reparto.clientes.*') ? 'border-horno text-horno' : 'border-transparent text-masa-madre hover:text-corteza' }}">
        Clientes
    </a>
    <a href="{{ route('reparto.repartidores.index') }}"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ request()->routeIs('reparto.repartidores.*') ? 'border-horno text-horno' : 'border-transparent text-masa-madre hover:text-corteza' }}">
        Repartidores
    </a>
</div>

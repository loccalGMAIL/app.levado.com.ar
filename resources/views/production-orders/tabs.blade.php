{{-- Pestañas de Órdenes de producción: listado y pedidos recurrentes. --}}
<div class="flex items-center gap-1 border-b border-miga">
    <a href="{{ route('production-orders.index') }}"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ request()->routeIs('production-orders.*') ? 'border-horno text-horno' : 'border-transparent text-masa-madre hover:text-corteza' }}">
        Órdenes
    </a>
    <a href="{{ route('production-requests.recurring.index') }}"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ request()->routeIs('production-requests.recurring.*') ? 'border-horno text-horno' : 'border-transparent text-masa-madre hover:text-corteza' }}">
        Pedidos recurrentes
    </a>
</div>

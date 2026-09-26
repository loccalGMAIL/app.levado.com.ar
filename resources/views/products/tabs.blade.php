{{-- Pestañas de Artículos: catálogo (por lista), matriz (todas las listas) e historial de producciones. --}}
<div class="flex items-center gap-1 border-b border-miga">
    <a href="{{ route('products.index') }}"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ request()->routeIs('products.index') ? 'border-corteza text-corteza' : 'border-transparent text-masa-madre hover:text-corteza' }}">
        Catálogo
    </a>
    <a href="{{ route('products.matrix') }}"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ request()->routeIs('products.matrix') ? 'border-corteza text-corteza' : 'border-transparent text-masa-madre hover:text-corteza' }}">
        Matriz de precios
    </a>
    <a href="{{ route('products.history') }}"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ request()->routeIs('products.history') ? 'border-corteza text-corteza' : 'border-transparent text-masa-madre hover:text-corteza' }}">
        Historial
    </a>
</div>

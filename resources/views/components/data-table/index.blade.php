{{--
    Listado con una sola fuente de render: se escribe únicamente la fila y el
    CSS decide si se ve como fila de tabla (desde md) o como card (por debajo).
    Reemplaza a x-responsive-table, que pedía el mismo dato dos veces —un slot
    de cards y otro de tabla— y dependía de que el x-data del padre declarara
    `mobileExpanded`.

    Uso:
    <x-data-table :paginator="$ingredients" total-label="ingrediente">
        <x-slot:head>
            <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
            <th class="px-4 py-3 font-medium">Estado</th>
        </x-slot:head>

        @foreach($ingredients as $ingredient)
            <x-data-table.row :dimmed="! $ingredient->active">
                <x-data-table.cell role="title">{{ $ingredient->name }}</x-data-table.cell>
                <x-data-table.cell role="badge"><x-status-badge :active="$ingredient->active" /></x-data-table.cell>
            </x-data-table.row>
        @endforeach
    </x-data-table>

    El x-data de la página sigue siendo el dueño de `editing` / `openEdit`: Alpine
    resuelve hacia arriba en la cadena de scopes, así que el x-data propio de este
    componente no los tapa.
--}}
@props(['paginator', 'totalLabel'])

<div class="data-table" x-data="{ mobileExpanded: false }" :data-view="mobileExpanded ? 'table' : 'cards'">

    <div class="data-table__surface bg-white rounded-lg shadow overflow-x-auto">
        <div x-show="mobileExpanded" class="md:hidden px-4 py-2 border-b border-miga">
            <button type="button" @click="mobileExpanded = false"
                class="text-sm text-masa-madre hover:text-corteza">
                ← Volver a cards
            </button>
        </div>

        <table class="w-full text-sm text-left">
            <thead class="bg-miga text-masa-madre border-b border-miga">
                <tr>{{ $head }}</tr>
            </thead>
            <tbody>{{ $slot }}</tbody>
        </table>

        @if($paginator->hasPages())
            <div class="px-4 py-3 border-t border-miga">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>

    <button type="button" x-show="!mobileExpanded" @click="mobileExpanded = true"
        class="md:hidden w-full py-2 text-sm text-masa-madre hover:text-corteza text-center">
        Ver tabla completa ↓
    </button>

    <p class="text-xs text-masa-madre mt-3">{{ $paginator->total() }} {{ $totalLabel }}(s) en total.</p>
</div>

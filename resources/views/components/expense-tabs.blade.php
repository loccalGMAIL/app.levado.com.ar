@php
    $tabs = [
        ['route' => 'fixed-costs.index', 'pattern' => 'fixed-costs.*', 'label' => 'Gastos Fijos'],
        ['route' => 'variable-expenses.index', 'pattern' => 'variable-expenses.*', 'label' => 'Gastos Variables'],
    ];
@endphp

<div class="flex gap-1 border-b border-miga">
    @foreach($tabs as $tab)
        <a href="{{ route($tab['route']) }}"
            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ request()->routeIs($tab['pattern']) ? 'border-horno text-horno' : 'border-transparent text-masa-madre hover:text-corteza' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>

<x-app-layout>
    <x-slot name="title">Historial</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            <div>
                <h2 class="text-base font-semibold text-corteza">Artículos</h2>
                <p class="text-sm text-masa-madre mt-0.5">Todas las producciones de todos tus artículos, la más reciente primero.</p>
            </div>

            @include('products.tabs')

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Artículo</label>
                    <select name="product" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todos los artículos</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected((string) request('product') === (string) $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Estado</label>
                    <select name="status" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todos</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Desde</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Hasta</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request()->hasAny(['product', 'status', 'from', 'to']))
                    <a href="{{ route('products.history') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @if($productions->isEmpty())
                <x-empty-state>
                    @if(request()->hasAny(['product', 'status', 'from', 'to']))
                        No hay producciones que coincidan con el filtro.
                    @else
                        Todavía no hay producciones registradas. Se cargan desde una orden de producción.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$productions" total-label="producción" total-suffix="(es)">
                    <x-slot:head>
                        <th class="px-4 py-3 font-medium">Fecha</th>
                        <th class="px-4 py-3 font-medium">Artículo</th>
                        <th class="px-4 py-3 font-medium text-right">Cantidad</th>
                        <th class="px-4 py-3 font-medium text-right">Costo total</th>
                        <th class="px-4 py-3 font-medium">Origen</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3"></th>
                    </x-slot:head>

                    @foreach($productions as $production)
                        <x-data-table.row :dimmed="$production->isCancelled()">
                            <x-data-table.cell role="subtitle" class="text-masa-madre whitespace-nowrap">{{ $production->produced_at?->format('d/m/Y H:i') }}</x-data-table.cell>
                            <x-data-table.cell role="title">
                                <a href="{{ route('production.show', $production) }}" class="hover:underline">
                                    {{ $production->product?->name ?? '—' }}
                                </a>
                            </x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" class="text-corteza font-mono">
                                {{ number_format($production->quantity, 2, ',', '.') }} {{ $production->unit->short() }}
                            </x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" label="Costo:" class="text-corteza font-mono">
                                <span class="dt-card-only">$ </span>{{ number_format($production->total_cost, 2, ',', '.') }}
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle">
                                @if($production->productionOrder)
                                    <a href="{{ route('production-orders.show', $production->productionOrder) }}" class="text-horno hover:underline">
                                        {{ $production->productionOrder->numberLabel() }}
                                    </a>
                                @else
                                    <span class="text-masa-madre">Ad-hoc</span>
                                @endif
                            </x-data-table.cell>
                            <x-data-table.cell role="badge">
                                <x-production-status-badge :status="$production->status" />
                            </x-data-table.cell>
                            <x-data-table.cell role="actions">
                                <div class="dt-actions">
                                    <a href="{{ route('production.show', $production) }}" class="dt-action !text-horno">Ver</a>
                                </div>
                            </x-data-table.cell>
                        </x-data-table.row>
                    @endforeach
                </x-data-table>
            @endif

        </div>
    </div>
</x-app-layout>

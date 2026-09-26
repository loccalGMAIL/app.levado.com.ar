<x-app-layout>
    <x-slot name="title">Historial</x-slot>

    <div class="py-8 px-6 lg:px-8" x-data="{ mobileExpanded: false }">
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
                <x-responsive-table>
                    <x-slot:cards>
                    @foreach($productions as $production)
                        <a href="{{ route('production.show', $production) }}"
                            class="block bg-white border border-miga rounded-lg p-4 shadow-sm {{ $production->isCancelled() ? 'opacity-60' : '' }}">
                            <div class="flex items-start justify-between">
                                <div class="font-medium text-corteza">{{ $production->product?->name ?? '—' }}</div>
                                <x-production-status-badge :status="$production->status" />
                            </div>
                            <div class="text-xs text-masa-madre mt-1">
                                {{ $production->produced_at?->format('d/m/Y H:i') }}
                                @if($production->productionOrder)
                                    · {{ $production->productionOrder->numberLabel() }}
                                @else
                                    · Ad-hoc
                                @endif
                            </div>
                            <div class="mt-2 flex items-center justify-between text-sm">
                                <span class="text-corteza font-mono">{{ number_format($production->quantity, 2, ',', '.') }} {{ $production->unit->short() }}</span>
                                <span class="text-masa-madre">Costo <span class="font-mono text-corteza">$ {{ number_format($production->total_cost, 2, ',', '.') }}</span></span>
                            </div>
                        </a>
                    @endforeach
                    </x-slot:cards>

                    <thead class="bg-miga text-masa-madre border-b border-miga">
                        <tr>
                            <th class="px-4 py-3 font-medium">Fecha</th>
                            <th class="px-4 py-3 font-medium">Artículo</th>
                            <th class="px-4 py-3 font-medium text-right">Cantidad</th>
                            <th class="px-4 py-3 font-medium text-right">Costo total</th>
                            <th class="px-4 py-3 font-medium">Origen</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($productions as $production)
                            <tr class="{{ $production->isCancelled() ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3 text-masa-madre whitespace-nowrap">{{ $production->produced_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 font-medium text-corteza">
                                    <a href="{{ route('production.show', $production) }}" class="hover:underline">
                                        {{ $production->product?->name ?? '—' }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-right text-corteza font-mono">
                                    {{ number_format($production->quantity, 2, ',', '.') }} {{ $production->unit->short() }}
                                </td>
                                <td class="px-4 py-3 text-right text-corteza font-mono">
                                    {{ number_format($production->total_cost, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($production->productionOrder)
                                        <a href="{{ route('production-orders.show', $production->productionOrder) }}" class="text-horno hover:underline">
                                            {{ $production->productionOrder->numberLabel() }}
                                        </a>
                                    @else
                                        <span class="text-masa-madre">Ad-hoc</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-production-status-badge :status="$production->status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('production.show', $production) }}" class="text-sm text-horno hover:underline">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <x-slot:footer>
                        @if($productions->hasPages())
                            <div class="px-4 py-3 border-t border-miga">
                                {{ $productions->links() }}
                            </div>
                        @endif
                    </x-slot:footer>
                </x-responsive-table>

                <p class="text-xs text-masa-madre">{{ $productions->total() }} producción(es) en total.</p>
            @endif

        </div>
    </div>
</x-app-layout>

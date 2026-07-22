<x-app-layout>
    <x-slot name="title">Producción</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Producción</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Fabricación de elaborados: descuenta insumos y suma stock del producto.</p>
                </div>
                @can('manage-costs')
                    <a href="{{ route('production.create') }}"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors shrink-0">
                        + Producir
                    </a>
                @endcan
            </div>

            @if($productions->isEmpty())
                <x-empty-state>
                    Todavía no registraste producciones. Producí tu primer elaborado.
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
                            <th class="px-4 py-3 font-medium">Producto</th>
                            <th class="px-4 py-3 font-medium text-right">Cantidad</th>
                            <th class="px-4 py-3 font-medium text-right">Costo total</th>
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
                                    <x-production-status-badge :status="$production->status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('production.show', $production) }}"
                                        class="text-sm text-horno hover:underline">Ver</a>
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

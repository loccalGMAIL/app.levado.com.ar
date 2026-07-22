<x-app-layout>
    <x-slot name="title">Producción</x-slot>

    @php
        $consumed = $movements->filter(fn ($m) => (float) $m->quantity < 0);
        $output = $movements->first(fn ($m) => (float) $m->quantity > 0);
    @endphp

    <div class="py-8 px-6 lg:px-8 max-w-3xl mx-auto space-y-6">

        <div class="flex items-start justify-between gap-4">
            <div>
                <a href="{{ route('production.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">← Producción</a>
                <h2 class="text-base font-semibold text-corteza mt-2">{{ $production->product?->name ?? 'Producción' }}</h2>
                <p class="text-sm text-masa-madre mt-0.5">
                    {{ $production->produced_at?->format('d/m/Y H:i') }}
                    @if($production->user) · {{ $production->user->name }} @endif
                    @if($production->recipe) · receta {{ $production->recipe->name }} @endif
                </p>
            </div>
            <x-production-status-badge :status="$production->status" class="mt-1" />
        </div>

        @if($production->isCancelled())
            <div class="bg-gray-50 border border-gray-200 rounded-lg px-5 py-3 text-sm text-gray-600">
                Producción anulada el {{ $production->cancelled_at?->format('d/m/Y H:i') }}. Sus movimientos de stock fueron revertidos.
            </div>
        @endif

        {{-- Resumen --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div class="bg-white border border-miga rounded-lg p-4 shadow-sm">
                <div class="text-xs text-masa-madre">Producido</div>
                <div class="mt-1 font-mono text-corteza">{{ number_format($production->quantity, 2, ',', '.') }} {{ $production->unit->short() }}</div>
            </div>
            <div class="bg-white border border-miga rounded-lg p-4 shadow-sm">
                <div class="text-xs text-masa-madre">Costo total de insumos</div>
                <div class="mt-1 font-mono text-corteza">$ {{ number_format($production->total_cost, 2, ',', '.') }}</div>
            </div>
            <div class="bg-white border border-miga rounded-lg p-4 shadow-sm">
                <div class="text-xs text-masa-madre">Costo por unidad</div>
                <div class="mt-1 font-mono text-corteza">$ {{ number_format($production->unit_cost, 2, ',', '.') }}</div>
            </div>
        </div>

        @if($production->notes)
            <div class="bg-white border border-miga rounded-lg p-4 shadow-sm text-sm text-corteza">
                <span class="text-xs text-masa-madre block mb-1">Notas</span>
                {{ $production->notes }}
            </div>
        @endif

        {{-- Insumos consumidos --}}
        <div class="bg-white border border-miga rounded-lg shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-miga">
                <h3 class="text-sm font-semibold text-corteza">Insumos consumidos</h3>
            </div>
            @if($consumed->isEmpty())
                <p class="px-5 py-4 text-sm text-masa-madre">Esta receta no tiene insumos que descontar.</p>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-miga text-masa-madre">
                        <tr>
                            <th class="px-5 py-2 text-left font-medium">Insumo</th>
                            <th class="px-5 py-2 text-right font-medium">Cantidad</th>
                            <th class="px-5 py-2 text-right font-medium">Costo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($consumed as $m)
                            @php
                                $item = $m->stockable();
                                $unitLabel = $m->isIngredient() || $m->isProduct() ? $item?->unit->short() : 'u';
                                $qty = abs((float) $m->quantity);
                            @endphp
                            <tr>
                                <td class="px-5 py-2 text-corteza">{{ $item?->name ?? '—' }}</td>
                                <td class="px-5 py-2 text-right font-mono text-corteza">{{ number_format($qty, 3, ',', '.') }} {{ $unitLabel }}</td>
                                <td class="px-5 py-2 text-right font-mono text-masa-madre">{{ number_format($qty * (float) $m->unit_cost, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        @can('manage-costs')
            @if($production->isConfirmed())
                <div class="flex justify-end">
                    <form method="POST" action="{{ route('production.cancel', $production) }}"
                        onsubmit="return confirm('¿Anular esta producción? Se revertirán los movimientos de stock (insumos e ingreso del elaborado).');">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="px-4 py-2 text-sm border border-red-300 text-red-600 rounded-md hover:bg-red-50 transition-colors">
                            Anular producción
                        </button>
                    </form>
                </div>
            @endif
        @endcan

    </div>
</x-app-layout>

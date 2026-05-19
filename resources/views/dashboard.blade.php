<x-app-layout>
    <x-slot name="title">Inicio</x-slot>

    <div class="py-8 px-6 lg:px-8 space-y-6">

        {{-- Tarjetas resumen --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-xs text-masa-madre">Recetas activas</p>
                <p class="mt-1 text-2xl font-semibold text-corteza">{{ $activeRecipeCount }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-xs text-masa-madre">Gastos fijos / mes</p>
                <p class="mt-1 text-lg font-semibold text-corteza font-mono">
                    $ {{ number_format((float)$totalFixedCosts, 2, ',', '.') }}
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-xs text-masa-madre">Horas productivas / mes</p>
                <p class="mt-1 text-lg font-semibold text-corteza font-mono">
                    {{ $productiveHours }} h
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-xs text-masa-madre">Overhead / hora</p>
                <p class="mt-1 text-lg font-semibold text-corteza font-mono">
                    @if($overheadPerHour !== null)
                        $ {{ number_format($overheadPerHour, 2, ',', '.') }}
                    @else
                        <span class="text-sm text-masa-madre">—</span>
                    @endif
                </p>
                @if($productiveHours === 0)
                    <p class="mt-1 text-xs text-amber-600">
                        <a href="{{ route('business.edit') }}" class="hover:underline">Configurar horas productivas →</a>
                    </p>
                @endif
            </div>
        </div>

        {{-- Tabla de rentabilidad --}}
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-corteza">Rentabilidad por receta</h2>
                <a href="{{ route('recipes.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline">
                    Ver todas las recetas →
                </a>
            </div>

            @if($recipeRows->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    Todavía no hay recetas.
                    @can('manage-costs')
                        <a href="{{ route('recipes.index') }}" class="text-corteza hover:underline">Crear la primera receta →</a>
                    @endcan
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">Receta</th>
                                <th class="px-4 py-3 font-medium text-right">Rinde</th>
                                <th class="px-4 py-3 font-medium text-right">Costo total</th>
                                <th class="px-4 py-3 font-medium text-right">Costo / u</th>
                                <th class="px-4 py-3 font-medium text-right">Precio venta / u</th>
                                <th class="px-4 py-3 font-medium text-right">Margen</th>
                                <th class="px-4 py-3 font-medium text-right">Margen %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($recipeRows as $row)
                                @php $recipe = $row['recipe']; @endphp
                                <tr class="{{ $recipe->active ? '' : 'opacity-40' }}">
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        <a href="{{ route('recipes.show', $recipe) }}" class="hover:underline">
                                            {{ $recipe->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-corteza">
                                        {{ number_format((float)$recipe->yield_quantity, 0, ',', '.') }} {{ $recipe->yield_unit->short() }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-corteza">
                                        $ {{ number_format($row['total_cost'], 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-corteza">
                                        @if($row['cost_per_unit'] !== null)
                                            $ {{ number_format($row['cost_per_unit'], 2, ',', '.') }}
                                        @else
                                            <span class="text-masa-madre">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-corteza">
                                        @if($row['selling_price'] !== null)
                                            $ {{ number_format($row['selling_price'], 2, ',', '.') }}
                                        @else
                                            <a href="{{ route('recipes.show', $recipe) }}"
                                                class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                Agregar →
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        @if($row['margin'] !== null)
                                            <span class="{{ $row['margin'] >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                                $ {{ number_format($row['margin'], 2, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="text-masa-madre">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        @if($row['margin_pct'] !== null)
                                            @php
                                                $pct = $row['margin_pct'];
                                                $color = $pct >= 30 ? 'text-green-600' : ($pct >= 15 ? 'text-amber-600' : 'text-red-500');
                                            @endphp
                                            <span class="{{ $color }} font-medium">
                                                {{ number_format($pct, 1, ',', '.') }} %
                                            </span>
                                        @else
                                            <span class="text-masa-madre">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-masa-madre">
                    Verde ≥ 30 % · Amarillo 15–29 % · Rojo &lt; 15 %. Para editar el precio de venta, entrá a la receta.
                </p>
            @endif
        </div>

        @if($activeRecipeCount > 0 && $packagingCount === 0)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-amber-800">¿Usás envases en tus productos?</p>
                <p class="text-xs text-amber-700 mt-0.5">Cargalos para incluir su costo en el cálculo de cada receta.</p>
            </div>
            <a href="{{ route('packaging.index') }}" class="text-sm text-amber-800 hover:underline font-medium shrink-0 ml-4">
                Ir a Envases →
            </a>
        </div>
        @endif

    </div>
</x-app-layout>

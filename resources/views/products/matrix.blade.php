<x-app-layout>
    <x-slot name="title">Matriz de precios</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Artículos</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Todos los precios de tus artículos, lista por lista. Hacé clic en una celda para editar.</p>
                </div>
                @can('manage-costs')
                    <div class="flex items-center gap-3 shrink-0">
                        <form method="POST" action="{{ route('price-lists.apply-all-suggestions') }}"
                            onsubmit="return confirm('¿Aplicar todas las sugerencias pendientes? Solo se completarán las celdas vacías.')">
                            @csrf
                            <button type="submit"
                                class="px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                                Aplicar sugerencias
                            </button>
                        </form>
                    </div>
                @endcan
            </div>

            @include('products.tabs')

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre, SKU o código..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <select name="type"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todos los tipos</option>
                    <option value="manufactured" @selected(request('type') === 'manufactured')>Elaborados</option>
                    <option value="resale"       @selected(request('type') === 'resale')>Reventa</option>
                </select>
                @if($categories->isNotEmpty())
                    <select name="category"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todas las categorías</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) request('category') === (string) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('type') || request('category'))
                    <a href="{{ route('products.matrix') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @if($products->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('type') || request('category'))
                        No se encontraron artículos con esos filtros.
                    @else
                        Todavía no hay artículos activos para mostrar.
                    @endif
                </x-empty-state>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">Artículo</th>
                                <th class="px-4 py-3 font-medium text-right">Costo / u</th>
                                @foreach($priceLists as $list)
                                    <th class="px-4 py-3 font-medium text-right whitespace-nowrap">
                                        {{ $list->name }}
                                        @if($list->is_default)
                                            <span class="block text-[10px] font-normal normal-case tracking-normal">lista base</span>
                                        @elseif($list->adjustment_pct !== null)
                                            <span class="block text-[10px] font-normal normal-case tracking-normal">
                                                {{ number_format($list->adjustment_pct, 2, ',', '.') }} % s/ base
                                            </span>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($products as $product)
                                @php
                                    $costPerUnit = $costsPerUnit[$product->id] ?? null;
                                    $productPrices = $prices[$product->id] ?? collect();
                                    $productPolicies = $policies[$product->id] ?? collect();
                                    $basePrice = isset($productPrices[$defaultList->id]) ? (float) $productPrices[$defaultList->id] : null;
                                @endphp
                                <tr class="{{ $product->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 font-medium text-corteza whitespace-nowrap">
                                        <span>{{ $product->name }}</span>
                                        <span class="ml-1"><x-product-type-badge :type="$product->type" /></span>
                                        @if($product->category)
                                            <span class="block text-[11px] text-masa-madre font-normal">{{ $product->category->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-corteza whitespace-nowrap">
                                        @if($costPerUnit !== null)
                                            $ {{ number_format($costPerUnit, 2, ',', '.') }}
                                        @else
                                            <span class="text-masa-madre">—</span>
                                        @endif
                                    </td>
                                    @foreach($priceLists as $list)
                                        @php
                                            $cellPrice = isset($productPrices[$list->id]) ? (float) $productPrices[$list->id] : null;

                                            $suggested = null;
                                            if ($cellPrice === null && ! $list->is_default && $list->adjustment_pct !== null && $basePrice !== null) {
                                                $suggested = round($basePrice * (1 + (float) $list->adjustment_pct / 100), 2);
                                            }

                                            $marginPct = null;
                                            $marginColor = 'text-masa-madre';
                                            if ($cellPrice !== null && $costPerUnit !== null && $cellPrice > 0) {
                                                $marginPct = (($cellPrice - $costPerUnit) / $cellPrice) * 100;
                                                $marginColor = $marginPct >= 30 ? 'text-green-600' : ($marginPct >= 15 ? 'text-amber-600' : 'text-red-500');
                                            }
                                            $policy = $productPolicies[$list->id] ?? ['type' => 'manual', 'value' => null];
                                        @endphp
                                        <td class="px-4 py-3 text-right font-mono text-corteza whitespace-nowrap align-top">
                                            @can('manage-costs')
                                                <div
                                                    x-data="priceCell({
                                                        url: @js(route('products.prices.update', [$product, $list])),
                                                        price: {{ $cellPrice ?? 'null' }},
                                                        priceFormatted: '{{ $cellPrice !== null ? number_format($cellPrice, 2, ',', '.') : '' }}',
                                                        suggested: {{ $suggested ?? 'null' }},
                                                        suggestedFormatted: '{{ $suggested !== null ? number_format($suggested, 2, ',', '.') : '' }}',
                                                        marginPct: {{ $marginPct ?? 'null' }},
                                                        marginPctFormatted: '{{ $marginPct !== null ? number_format($marginPct, 1, ',', '.') : '' }}',
                                                        marginColor: '{{ $marginColor }}',
                                                        policyType: '{{ $policy['type'] }}',
                                                        policyValue: {{ $policy['value'] ?? 'null' }},
                                                    })">
                                                    <div @click="startEdit($event)"
                                                        class="cursor-pointer hover:text-horno select-none">
                                                        <span x-show="!saving && price !== null" x-text="'$ ' + priceFormatted"></span>
                                                        <span x-show="!saving && price === null && suggested !== null"
                                                            class="text-xs text-masa-madre italic">
                                                            $ <span x-text="suggestedFormatted"></span> sugerido
                                                        </span>
                                                        <span x-show="!saving && price === null && suggested === null"
                                                            class="text-xs text-masa-madre hover:text-corteza">
                                                            Agregar →
                                                        </span>
                                                        <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                                        <span x-show="hasPolicy" x-text="policyBadge"
                                                            class="block text-[10px] px-1.5 py-0.5 rounded bg-miga text-masa-madre font-sans leading-none mt-0.5"></span>
                                                        <span x-show="marginPct !== null"
                                                            :class="marginColor"
                                                            class="block text-[11px] font-medium"
                                                            x-text="marginPct !== null ? marginPctFormatted + ' %' : ''"></span>
                                                    </div>
                                                    <x-price-cell-editor />
                                                </div>
                                            @else
                                                @if($cellPrice !== null)
                                                    $ {{ number_format($cellPrice, 2, ',', '.') }}
                                                    @if($marginPct !== null)
                                                        <span class="block text-[11px] font-medium {{ $marginColor }}">
                                                            {{ number_format($marginPct, 1, ',', '.') }} %
                                                        </span>
                                                    @endif
                                                @else
                                                    <span class="text-masa-madre">—</span>
                                                @endif
                                            @endcan
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($products->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-masa-madre">
                    {{ $products->total() }} artículo(s). Margen: verde ≥ 30 % · amarillo 15–29 % · rojo &lt; 15 %.
                    Las celdas vacías muestran en gris el precio que surge del % de ajuste de cada lista sobre la base; al hacer clic podés confirmarlo o cambiarlo.
                </p>
            @endif

        </div>
    </div>
</x-app-layout>

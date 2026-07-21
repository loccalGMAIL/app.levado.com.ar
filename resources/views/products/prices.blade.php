<x-app-layout>
    <x-slot name="title">Precios de reventa</x-slot>

    <div class="py-8 px-6 lg:px-8">
        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Precios de reventa</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Precios de tus productos de reventa, lista por lista. Hacé clic en una celda para editar.</p>
                </div>
                <a href="{{ route('price-lists.index') }}"
                    class="px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors shrink-0">
                    ← Listas de precios
                </a>
            </div>

            {{-- Solapas: elaborados (recetas) vs. reventa (productos) --}}
            <div class="flex gap-1 border-b border-miga text-sm">
                <a href="{{ route('price-lists.matrix') }}"
                    class="px-4 py-2 -mb-px border-b-2 border-transparent text-masa-madre hover:text-corteza">
                    Elaborados (recetas)
                </a>
                <span class="px-4 py-2 -mb-px border-b-2 border-corteza font-medium text-corteza">
                    Reventa
                </span>
            </div>

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar producto por nombre..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Buscar
                </button>
                @if(request('search'))
                    <a href="{{ route('products.prices.matrix') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @if($products->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    @if(request('search'))
                        No se encontraron productos de reventa con ese nombre.
                    @else
                        Todavía no hay productos de reventa activos. Cargá uno en <a href="{{ route('products.index') }}" class="text-corteza hover:underline">Artículos</a>.
                    @endif
                </div>
            @else
                @php
                    $dirToggle = request('dir') === 'desc' ? 'asc' : 'desc';
                    $sortUrl = request()->url() . '?' . http_build_query(
                        array_merge(request()->except(['dir', 'page']), ['dir' => $dirToggle])
                    );
                @endphp
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    <a href="{{ $sortUrl }}" class="hover:text-corteza inline-flex items-center gap-1">
                                        Producto <span class="text-xs">{{ request('dir') === 'desc' ? '↓' : '↑' }}</span>
                                    </a>
                                </th>
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
                                    $basePrice = isset($productPrices[$defaultList->id]) ? (float) $productPrices[$defaultList->id] : null;
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 font-medium text-corteza whitespace-nowrap">
                                        {{ $product->name }}
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
                                        @endphp
                                        <td class="px-4 py-3 text-right font-mono text-corteza whitespace-nowrap align-top">
                                            @can('manage-costs')
                                                <div
                                                    x-data="{
                                                        editing: false,
                                                        saving: false,
                                                        isDirty: false,
                                                        price: {{ $cellPrice ?? 'null' }},
                                                        priceFormatted: '{{ $cellPrice !== null ? number_format($cellPrice, 2, ',', '.') : '' }}',
                                                        suggested: {{ $suggested ?? 'null' }},
                                                        suggestedFormatted: '{{ $suggested !== null ? number_format($suggested, 2, ',', '.') : '' }}',
                                                        marginPct: {{ $marginPct ?? 'null' }},
                                                        marginPctFormatted: '{{ $marginPct !== null ? number_format($marginPct, 1, ',', '.') : '' }}',
                                                        marginColor: '{{ $marginColor }}',
                                                        startEdit() {
                                                            this.isDirty = false;
                                                            const initial = this.price ?? this.suggested;
                                                            this.$refs.priceInput.value = initial !== null ? parseFloat(initial).toFixed(2) : '';
                                                            this.isDirty = this.price === null && this.suggested !== null;
                                                            this.editing = true;
                                                            this.$nextTick(() => this.$refs.priceInput.select());
                                                        },
                                                        async savePrice() {
                                                            if (this.saving) return;
                                                            if (!this.isDirty) { this.editing = false; return; }
                                                            const raw = this.$refs.priceInput.value.trim();
                                                            const payload = raw !== '' ? raw : null;
                                                            this.saving = true;
                                                            this.editing = false;
                                                            try {
                                                                const res = await fetch('{{ route('products.prices.update', [$product, $list]) }}', {
                                                                    method: 'PATCH',
                                                                    headers: {
                                                                        'Content-Type': 'application/json',
                                                                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                                        'Accept': 'application/json',
                                                                    },
                                                                    body: JSON.stringify({ price: payload })
                                                                });
                                                                const data = await res.json();
                                                                this.price = data.selling_price;
                                                                this.priceFormatted = data.selling_price_formatted ?? '';
                                                                this.marginPct = data.margin_pct;
                                                                this.marginPctFormatted = data.margin_pct_formatted ?? '';
                                                                this.marginColor = data.margin_color ?? 'text-masa-madre';
                                                            } finally {
                                                                this.saving = false;
                                                            }
                                                        }
                                                    }">
                                                    <div x-show="!editing && !saving"
                                                        @click="startEdit()"
                                                        class="cursor-pointer hover:text-horno select-none">
                                                        <span x-show="price !== null" x-text="'$ ' + priceFormatted"></span>
                                                        <span x-show="price === null && suggested !== null"
                                                            class="text-xs text-masa-madre italic">
                                                            $ <span x-text="suggestedFormatted"></span> sugerido
                                                        </span>
                                                        <span x-show="price === null && suggested === null"
                                                            class="text-xs text-masa-madre hover:text-corteza">
                                                            Agregar →
                                                        </span>
                                                    </div>
                                                    <input
                                                        x-show="editing"
                                                        x-ref="priceInput"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        @input="isDirty = true"
                                                        @keydown.enter.prevent="savePrice()"
                                                        @keydown.escape="editing = false; isDirty = false"
                                                        @blur="savePrice()"
                                                        class="w-28 text-right text-sm border-gray-300 rounded px-1 py-0.5 focus:border-horno focus:ring-horno font-mono">
                                                    <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                                    <span x-show="marginPct !== null && !editing"
                                                        :class="marginColor"
                                                        class="block text-[11px] font-medium"
                                                        x-text="marginPct !== null ? marginPctFormatted + ' %' : ''"></span>
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
                    {{ $products->total() }} producto(s) de reventa. Margen: verde ≥ 30 % · amarillo 15–29 % · rojo &lt; 15 %.
                    Las celdas vacías muestran en gris el precio que surge del % de ajuste de cada lista sobre la base; al hacer clic podés confirmarlo o cambiarlo.
                </p>
            @endif

        </div>
    </div>
</x-app-layout>

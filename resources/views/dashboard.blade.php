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

            {{-- Buscador --}}
            <form method="GET" class="flex gap-3 items-end flex-wrap mb-4">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                @if($priceLists->count() > 1)
                    <select name="price_list" onchange="this.form.submit()"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        @foreach($priceLists as $list)
                            <option value="{{ $list->id }}" @selected($priceList->id === $list->id)>
                                Lista: {{ $list->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Buscar
                </button>
                @if(request('search'))
                    <a href="{{ route('dashboard') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
            @endphp

            @if($recipeRows->total() === 0)
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    @if(request('search'))
                        No se encontraron recetas con ese nombre.
                    @else
                        Todavía no hay recetas.
                        @can('manage-costs')
                            <a href="{{ route('recipes.index') }}" class="text-corteza hover:underline">Crear la primera receta →</a>
                        @endcan
                    @endif
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <x-sortable-th column="name" :sort="$sort" :dir="$dir">Receta</x-sortable-th>
                                <x-sortable-th column="yield_quantity" :sort="$sort" :dir="$dir" align="right">Rinde</x-sortable-th>
                                <th class="px-4 py-3 font-medium text-right">Costo total</th>
                                <x-sortable-th column="cost_per_unit" :sort="$sort" :dir="$dir" align="right">Costo / u</x-sortable-th>
                                <x-sortable-th column="selling_price" :sort="$sort" :dir="$dir" align="right">Precio ({{ $priceList->name }}) / u</x-sortable-th>
                                <x-sortable-th column="margin" :sort="$sort" :dir="$dir" align="right">Margen</x-sortable-th>
                                <x-sortable-th column="margin_pct" :sort="$sort" :dir="$dir" align="right">Margen %</x-sortable-th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($recipeRows as $row)
                                @php
                                    $recipe = $row['recipe'];
                                    $pct = $row['margin_pct'];
                                    $initMarginColor = $pct !== null
                                        ? ($pct >= 30 ? 'text-green-600' : ($pct >= 15 ? 'text-amber-600' : 'text-red-500'))
                                        : 'text-masa-madre';
                                    $initPriceInput     = $row['selling_price'] !== null ? number_format($row['selling_price'], 2, '.', '') : '';
                                    $initPriceFormatted = $row['selling_price'] !== null ? number_format($row['selling_price'], 2, ',', '.') : '';
                                    $initMarginFormatted    = $row['margin'] !== null ? number_format($row['margin'], 2, ',', '.') : '';
                                    $initMarginPctFormatted = $pct !== null ? number_format($pct, 1, ',', '.') : '';
                                @endphp
                                <tr
                                    x-data="{
                                        editing: false,
                                        saving: false,
                                        isDirty: false,
                                        price: {{ $row['selling_price'] ?? 'null' }},
                                        priceFormatted: '{{ $initPriceFormatted }}',
                                        margin: {{ $row['margin'] ?? 'null' }},
                                        marginFormatted: '{{ $initMarginFormatted }}',
                                        marginPct: {{ $row['margin_pct'] ?? 'null' }},
                                        marginPctFormatted: '{{ $initMarginPctFormatted }}',
                                        marginColor: '{{ $initMarginColor }}',
                                        startEdit() {
                                            this.isDirty = false;
                                            this.$refs.priceInput.value = this.price !== null ? parseFloat(this.price).toFixed(2) : '';
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
                                                const res = await fetch('{{ route('recipes.prices.update', [$recipe, $priceList]) }}', {
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
                                                this.margin = data.margin;
                                                this.marginFormatted = data.margin_formatted ?? '';
                                                this.marginPct = data.margin_pct;
                                                this.marginPctFormatted = data.margin_pct_formatted ?? '';
                                                this.marginColor = data.margin_color ?? 'text-masa-madre';
                                            } finally {
                                                this.saving = false;
                                            }
                                        }
                                    }"
                                    class="{{ $recipe->active ? '' : 'opacity-40' }}"
                                >
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        <a href="{{ route('recipes.show', $recipe) }}" class="hover:underline">
                                            {{ $recipe->name }}
                                            <x-semi-badge :is-semi-elaborate="$recipe->is_semi_elaborate" />
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
                                        @can('manage-costs')
                                            <div x-show="!editing && !saving"
                                                @click="startEdit()"
                                                class="cursor-pointer hover:text-horno select-none">
                                                <span x-show="price !== null"
                                                    x-text="'$ ' + priceFormatted"></span>
                                                <span x-show="price === null"
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
                                        @else
                                            @if($row['selling_price'] !== null)
                                                $ {{ $initPriceFormatted }}
                                            @else
                                                <span class="text-masa-madre">—</span>
                                            @endif
                                        @endcan
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        <span x-show="margin !== null"
                                            :class="margin !== null && margin >= 0 ? 'text-green-600' : 'text-red-500'"
                                            x-text="margin !== null ? '$ ' + marginFormatted : ''"></span>
                                        <span x-show="margin === null" class="text-masa-madre">—</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono">
                                        <span x-show="marginPct !== null"
                                            :class="marginColor"
                                            class="font-medium"
                                            x-text="marginPct !== null ? marginPctFormatted + ' %' : ''"></span>
                                        <span x-show="marginPct === null" class="text-masa-madre">—</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($recipeRows->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $recipeRows->links() }}
                        </div>
                    @endif
                </div>

                <p class="mt-2 text-xs text-masa-madre">
                    Verde ≥ 30 % · Amarillo 15–29 % · Rojo &lt; 15 %. Hacé clic en el precio de venta para editarlo.
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

<x-app-layout>
    <x-slot name="title">Combos</x-slot>

    @php
        $formatQuantity = fn ($quantity) => rtrim(rtrim(number_format((float) $quantity, 3, ',', '.'), '0'), ',');

        // Catálogo de componentes para el modal, indexado por id: nombre, unidad y costo
        // (el mismo costo total que usa el margen) para previsualizar el costo del combo.
        $componentCatalog = $components->mapWithKeys(fn ($product) => [$product->id => [
            'id'   => $product->id,
            'name' => $product->name,
            'unit' => $product->unit->short(),
            'cost' => $product->fullCost($overheadPerHour),
        ]]);

        $lineFor = fn ($productId, $quantity, $fallback = null) => [
            'product_id' => (int) $productId,
            'name'       => $componentCatalog[$productId]['name'] ?? $fallback?->name ?? 'Artículo no disponible',
            'unit'       => $componentCatalog[$productId]['unit'] ?? $fallback?->unit->short() ?? '',
            'cost'       => $componentCatalog[$productId]['cost'] ?? $fallback?->fullCost($overheadPerHour),
            'quantity'   => (float) $quantity,
        ];

        $comboFormDefault = ['id' => null, 'name' => '', 'product_category_id' => '', 'barcode' => '', 'lines' => []];
        $errorsInComboForm = $errors->any() && old('_form') === 'combo';
        $comboFormOnError = $errorsInComboForm ? [
            'id'                  => old('combo_id'),
            'name'                => old('name'),
            'product_category_id' => old('product_category_id') ?? '',
            'barcode'             => old('barcode') ?? '',
            'lines'               => collect(old('lines', []))
                ->filter(fn ($line) => ! empty($line['product_id']))
                ->map(fn ($line) => $lineFor($line['product_id'], $line['quantity'] ?? 1))
                ->values()->all(),
        ] : $comboFormDefault;

        $comboPayload = fn ($combo) => [
            'id'                  => $combo->id,
            'name'                => $combo->name,
            'product_category_id' => $combo->product_category_id ?? '',
            'barcode'             => $combo->barcode ?? '',
            'lines'               => $combo->comboLines->map(fn ($line) => $lineFor($line->product_id, $line->quantity, $line->component))->values()->all(),
        ];
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            comboForm: {{ Js::from($comboFormOnError) }},
            catalog: {{ Js::from($componentCatalog) }},
            openCombo(record) {
                // Copia: editar y cancelar no debe dejar tocado el payload de la fila.
                this.comboForm = JSON.parse(JSON.stringify(record));
                $dispatch('open-modal', 'product-combo');
            },
            addComboLine(select) {
                const item = this.catalog[select.value];
                if (item) {
                    const existing = this.comboForm.lines.find(line => line.product_id === item.id);
                    if (existing) {
                        existing.quantity = Number(existing.quantity) + 1;
                    } else {
                        this.comboForm.lines.push({ product_id: item.id, name: item.name, unit: item.unit, cost: item.cost, quantity: 1 });
                    }
                }
                select._ts ? select._ts.clear(true) : (select.value = '');
            },
            get comboCost() {
                if (this.comboForm.lines.length === 0 || this.comboForm.lines.some(line => line.cost === null)) return null;
                return this.comboForm.lines.reduce((sum, line) => sum + line.cost * (Number(line.quantity) || 0), 0);
            },
            get comboCostFormatted() {
                return this.comboCost === null ? '—' : '$ ' + this.comboCost.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }">

        <div class="space-y-6">

            <x-list-header title="Artículos" subtitle="Combos: varios artículos vendidos juntos, con código y precio propios. El stock se descuenta de cada componente.">
                @can('manage-costs')
                    <button type="button"
                        @click="openCombo({{ Js::from($comboFormDefault) }})"
                        class="px-4 py-2 bg-tinta text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nuevo combo
                    </button>
                @endcan
            </x-list-header>

            @include('products.tabs')

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre o código..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <select name="status"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todos</option>
                    <option value="active"   @selected(request('status') === 'active')>Activos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                </select>
                @if($priceLists->count() > 1)
                    <select name="price_list" onchange="this.form.submit()"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno"
                        title="Lista de precios">
                        @foreach($priceLists as $list)
                            <option value="{{ $list->id }}" @selected($priceList->id === $list->id)>{{ $list->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="px-4 py-2 bg-tinta text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('products.combos.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            <x-input-error :messages="$errors->get('toggle')" />

            @if($combos->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('status'))
                        No se encontraron combos con esos filtros.
                    @else
                        Todavía no hay combos. Armá el primero con dos o más artículos.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$combos" total-label="combo">
                    <x-slot:head>
                        <th class="px-4 py-3 font-medium text-left">Código</th>
                        <th class="px-4 py-3 font-medium text-left">Combo</th>
                        <th class="px-4 py-3 font-medium text-left">Categoría</th>
                        <th class="px-4 py-3 font-medium text-right">Costo</th>
                        <th class="px-4 py-3 font-medium text-right">Precio ({{ $priceList->name }})</th>
                        <th class="px-4 py-3 font-medium text-right">Margen</th>
                        <th class="px-4 py-3 font-medium text-right" title="Combos que se pueden armar con el stock actual de sus componentes">Disponibles</th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($combos as $combo)
                        @php
                            $cost = $combo->fullCost($overheadPerHour);
                            $price = isset($priceMap[$combo->id]) ? (float) $priceMap[$combo->id] : null;
                            $marginPct = ($price !== null && $cost !== null && $price > 0) ? ($price - $cost) / $price * 100 : null;
                            $policy = $policyMap[$combo->id] ?? ['type' => 'manual', 'value' => null];
                            $available = $availability[$combo->id] ?? 0;
                        @endphp
                        <x-data-table.row :dimmed="! $combo->active"
                            x-data="priceCell({{ Js::from([
                                'url' => route('products.prices.update', [$combo, $priceList]),
                                'price' => $price,
                                'priceFormatted' => $price !== null ? number_format($price, 2, ',', '.') : '',
                                'marginPct' => $marginPct,
                                'marginPctFormatted' => $marginPct !== null ? number_format($marginPct, 1, ',', '.') : '',
                                'marginColor' => \App\Enums\MarginLevel::textClassFor($marginPct),
                                'policyType' => $policy['type'],
                                'policyValue' => $policy['value'] !== null ? (float) $policy['value'] : null,
                            ]) }})">
                            <x-data-table.cell role="meta" class="text-masa-madre text-xs font-mono">
                                {{ $combo->barcode }}
                            </x-data-table.cell>
                            <x-data-table.cell role="title">
                                @can('manage-costs')
                                    <button type="button" @click="openCombo({{ Js::from($comboPayload($combo)) }})"
                                        class="font-medium text-corteza hover:underline text-left">
                                        {{ $combo->name }}
                                    </button>
                                @else
                                    <span class="font-medium text-corteza">{{ $combo->name }}</span>
                                @endcan
                                <span class="block text-xs text-masa-madre font-normal">
                                    {{ $combo->comboLines->map(fn ($line) => $formatQuantity($line->quantity).' '.$line->component->unit->short().' '.$line->component->name)->implode(' + ') }}
                                </span>
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre text-xs">
                                {{ $combo->category?->name }}
                            </x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" label="Costo:" class="text-corteza font-mono">
                                {{ $cost !== null ? number_format($cost, 2, ',', '.') : '—' }}
                            </x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" label="Precio ({{ $priceList->name }}):" class="text-corteza font-mono">
                                @can('manage-costs')
                                    <div @click="startEdit($event)"
                                        class="cursor-pointer hover:text-horno select-none inline-flex items-center gap-1 md:flex-col md:items-end md:gap-0.5">
                                        <span x-show="!saving">
                                            <span x-show="price !== null" x-text="'$ ' + priceFormatted"></span>
                                            <span x-show="price === null" class="text-xs text-masa-madre hover:text-corteza">Agregar →</span>
                                        </span>
                                        <span x-show="saving" class="text-xs text-masa-madre">…</span>
                                        <span x-show="hasPolicy" x-text="policyBadge"
                                            class="text-[10px] px-1.5 py-0.5 rounded bg-miga text-masa-madre font-sans leading-none"></span>
                                        <svg class="ico w-3 h-3 text-masa-madre md:hidden" x-show="!hasPolicy" aria-hidden="true"><use href="{{ $iconSprite }}#i-pencil"></use></svg>
                                    </div>
                                @else
                                    {{ $price !== null ? '$ '.number_format($price, 2, ',', '.') : '' }}
                                @endcan
                            </x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" label="Margen:" class="font-mono" x-bind:class="marginColor">
                                <span x-show="marginPct !== null" x-text="marginPctFormatted + '%'"></span>
                                <span x-show="marginPct === null" class="text-masa-madre">—</span>
                            </x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" label="Disponibles:" class="font-mono {{ $available > 0 ? 'text-corteza' : 'text-red-600' }}">
                                {{ $available }}
                            </x-data-table.cell>
                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions">
                                        <button type="button" @click="openCombo({{ Js::from($comboPayload($combo)) }})"
                                            aria-label="Editar combo" title="Editar combo"
                                            class="dt-action">
                                            <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-pencil"></use></svg>
                                            <span class="dt-card-only">Editar</span>
                                        </button>
                                        <form method="POST" action="{{ route('products.toggle-active', $combo) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                aria-label="{{ $combo->active ? 'Desactivar' : 'Activar' }}" title="{{ $combo->active ? 'Desactivar' : 'Activar' }}"
                                                class="dt-action {{ $combo->active ? 'dt-action--danger' : 'dt-action--success' }}">
                                                <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-{{ $combo->active ? 'eye-off' : 'check' }}"></use></svg>
                                                <span class="dt-card-only">{{ $combo->active ? 'Desactivar' : 'Activar' }}</span>
                                            </button>
                                        </form>
                                    </div>
                                </x-data-table.cell>
                            @endcan
                        </x-data-table.row>
                    @endforeach
                </x-data-table>
            @endif

        </div>

        @can('manage-costs')
            @include('products.modals.combo')
        @endcan

    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="title">Artículos</x-slot>

    @php
        $errorFields = ['name', 'type', 'recipe_id', 'recipe_quantity', 'product_category_id', 'unit', 'cost_per_unit', 'costing_method', 'barcode'];
        $errorsInCreate = $errors->hasAny($errorFields) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny($errorFields) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'type' => '', 'recipe_id' => '', 'recipe_quantity' => 1, 'recipe_url' => null, 'product_category_id' => '', 'unit' => '', 'cost_per_unit' => '', 'costing_method' => '', 'barcode' => ''];
        $editingOnError = $errorsInEdit ? [
            'id'                  => old('product_id'),
            'name'                => old('name'),
            'type'                => old('type'),
            'recipe_id'           => old('recipe_id'),
            'recipe_url'          => old('recipe_id') ? route('recipes.show', old('recipe_id')) : null,
            'recipe_quantity'     => old('recipe_quantity', 1),
            'product_category_id' => old('product_category_id'),
            'unit'                => old('unit'),
            'cost_per_unit'       => old('cost_per_unit'),
            'costing_method'      => old('costing_method'),
            'barcode'             => old('barcode'),
        ] : $editingDefault;

        // Payload de edición en un solo lugar: los tres disparadores (card, nombre e
        // ícono) lo reusan para que no se desincronicen y borren campos al guardar.
        $editPayload = fn ($product) => [
            'id'                  => $product->id,
            'name'                => $product->name,
            'type'                => $product->type->value,
            'recipe_id'           => $product->recipe_id ?? '',
            'recipe_url'          => $product->recipe_id ? route('recipes.show', $product->recipe_id) : null,
            'recipe_quantity'     => $product->recipe_quantity !== null ? (float) $product->recipe_quantity : 1,
            'product_category_id' => $product->product_category_id ?? '',
            'unit'                => $product->unit->value,
            'cost_per_unit'       => $product->cost_per_unit !== null ? round((float) $product->cost_per_unit, 2) : '',
            'costing_method'      => $product->costing_method?->value ?? '',
            'barcode'             => $product->barcode ?? '',
        ];
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            // Asignación masiva de categoría: sólo en la tabla (no en las
            // cards) — clasificar 190+ artículos es tarea de escritorio.
            // x-model en checkbox de array guarda strings, por eso pageIds
            // se siembra como strings (comparar contra números da siempre
            // falso). No sobrevive el cambio de página, a propósito.
            selectedIds: [],
            pageIds: {{ Js::from($products->pluck('id')->map(fn ($id) => (string) $id)->all()) }},
            get allPageSelected() { return this.pageIds.length > 0 && this.pageIds.every(id => this.selectedIds.includes(id)); },
            togglePage(on) {
                this.selectedIds = on
                    ? [...new Set([...this.selectedIds, ...this.pageIds])]
                    : this.selectedIds.filter(id => ! this.pageIds.includes(id));
            },
            clearSelection() { this.selectedIds = []; },
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'product-edit');
            },
            // El historial se trae a demanda: son N filas por artículo y viajar en
            // el payload del listado inflaría el HTML para algo que casi no se abre.
            costHistory: { name: '', isResale: true, loading: false, failed: false, rows: [] },
            async openCostHistory(url, name) {
                this.costHistory = { name, isResale: true, loading: true, failed: false, rows: [] };
                $dispatch('open-modal', 'product-cost-history');
                try {
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error(res.status);
                    const data = await res.json();
                    this.costHistory.isResale = data.is_resale;
                    this.costHistory.rows = data.rows;
                } catch (e) {
                    this.costHistory.failed = true;
                } finally {
                    this.costHistory.loading = false;
                }
            }
        }">

        <div class="space-y-6">

            <x-list-header title="Artículos" subtitle="Productos que vendés: elaborados (desde una receta) y de reventa.">
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'product-categories')"
                        class="px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                        Categorías
                    </button>
                    <button type="button" id="btn-nuevo-producto"
                        @click="$dispatch('open-modal', 'product-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nuevo artículo
                    </button>
                @endcan
            </x-list-header>

            @include('products.tabs')

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre o código..."
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
                        <option value="sin" @selected(request('category') === 'sin')>Sin categoría</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected((string) request('category') === (string) $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                @endif
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
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('status') || request('type') || request('category'))
                    <a href="{{ route('products.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @can('manage-costs')
                <div x-show="selectedIds.length > 0" x-cloak
                    class="flex items-center gap-3 bg-miga rounded-md px-4 py-2 text-sm">
                    <span class="text-corteza"><span x-text="selectedIds.length"></span> artículo(s) seleccionados (de esta página)</span>
                    <button type="button" @click="$dispatch('open-modal', 'product-bulk-category')"
                        class="px-3 py-1 bg-corteza text-white text-xs rounded-md hover:bg-horno transition-colors">
                        Asignar categoría
                    </button>
                    <button type="button" @click="clearSelection()" class="text-masa-madre hover:text-corteza hover:underline">
                        Limpiar selección
                    </button>
                </div>
            @endcan

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
            @endphp

            @if($products->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('status') || request('type') || request('category'))
                        No se encontraron artículos con esos filtros.
                    @else
                        Todavía no hay artículos. Agregá el primero.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$products" total-label="artículo">
                    <x-slot:head>
                        @can('manage-costs')
                            <th class="px-4 py-3 w-8">
                                <input type="checkbox" :checked="allPageSelected" @change="togglePage($event.target.checked)"
                                    aria-label="Seleccionar todos los de esta página"
                                    class="rounded border-gray-300 text-horno focus:ring-horno">
                            </th>
                        @endcan
                        <x-sortable-th column="code" :sort="$sort" :dir="$dir">Código</x-sortable-th>
                        <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                        <x-sortable-th column="type" :sort="$sort" :dir="$dir">Tipo</x-sortable-th>
                        <x-sortable-th column="category" :sort="$sort" :dir="$dir">Categoría</x-sortable-th>
                        <x-sortable-th column="unit" :sort="$sort" :dir="$dir">Unidad</x-sortable-th>
                        <th class="px-4 py-3 font-medium text-right">Costo/u</th>
                        <x-sortable-th column="price" :sort="$sort" :dir="$dir" align="right">Precio ({{ $priceList->name }})/u</x-sortable-th>
                        <th class="px-4 py-3 font-medium text-right">Margen</th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($products as $product)
                        @php
                            $cost = $product->fullCost($overheadPerHour);
                            $price = isset($priceMap[$product->id]) ? (float) $priceMap[$product->id] : null;
                            $marginPct = ($price !== null && $cost !== null && $price > 0) ? ($price - $cost) / $price * 100 : null;
                            $marginColor = $marginPct === null ? 'text-masa-madre' : ($marginPct >= 30 ? 'text-green-600' : ($marginPct >= 15 ? 'text-amber-600' : 'text-red-500'));
                            $initPriceFormatted = $price !== null ? number_format($price, 2, ',', '.') : '';
                            $initMarginPctFormatted = $marginPct !== null ? number_format($marginPct, 1, ',', '.') : '';
                            $policy = $policyMap[$product->id] ?? ['type' => 'manual', 'value' => null];
                        @endphp
                        <x-data-table.row :dimmed="! $product->active"
                            x-data="priceCell({{ Js::from([
                                'url' => route('products.prices.update', [$product, $priceList]),
                                'price' => $price,
                                'priceFormatted' => $initPriceFormatted,
                                'marginPct' => $marginPct,
                                'marginPctFormatted' => $initMarginPctFormatted,
                                'marginColor' => $marginColor,
                                'policyType' => $policy['type'],
                                'policyValue' => $policy['value'] !== null ? (float) $policy['value'] : null,
                            ]) }})">
                            @can('manage-costs')
                                {{-- La selección masiva es de escritorio: no entra en la card. --}}
                                <x-data-table.cell role="meta" cards="hide">
                                    <input type="checkbox" value="{{ $product->id }}" x-model="selectedIds"
                                        aria-label="Seleccionar {{ $product->name }}"
                                        class="rounded border-gray-300 text-horno focus:ring-horno">
                                </x-data-table.cell>
                            @endcan
                            <x-data-table.cell role="meta" class="text-masa-madre text-xs font-mono">
                                {{ $product->barcode ?? $product->sku }}
                            </x-data-table.cell>
                            <x-data-table.cell role="title">
                                @can('manage-costs')
                                    <button type="button" @click="openEdit({{ Js::from($editPayload($product)) }})"
                                        class="font-medium text-corteza hover:underline text-left">
                                        {{ $product->name }}
                                    </button>
                                @else
                                    <span class="font-medium text-corteza">{{ $product->name }}</span>
                                @endcan
                                @if($product->isManufactured() && $product->recipe && ($product->isRecipePresentation() || $product->recipe->name !== $product->name))
                                    <span class="dt-card-only text-xs text-masa-madre font-normal">
                                        {{ $product->isRecipePresentation() ? rtrim(rtrim(number_format((float) $product->recipe_quantity, 3, ",", "."), "0"), ",").' '.$product->recipe->yield_unit->short().' de ' : '' }}{{ $product->recipe->name }}
                                    </span>
                                @endif
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle">
                                <x-product-type-badge :type="$product->type" />
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre text-xs">
                                @if($product->category)
                                    <span class="inline-flex items-center gap-1">
                                        {{ $product->category->name }}
                                        @unless($product->category->producible)
                                            <span class="text-[10px] text-gray-400 whitespace-nowrap" title="Excluido de los selectores de Producción">no se produce</span>
                                        @endunless
                                    </span>
                                @endif
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre">{{ $product->unit->short() }}</x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" label="Costo/u:" class="text-corteza font-mono">
                                {{ $cost !== null ? number_format($cost, 2, ',', '.') : '' }}
                                @can('manage-costs')
                                    <x-cost-source-badge :product="$product" :history-url="route('products.cost-history', $product)" class="inline-flex align-middle" />
                                @endcan
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
                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions">
                                        <button type="button" @click="openEdit({{ Js::from($editPayload($product)) }})"
                                            aria-label="Editar artículo" title="Editar artículo"
                                            class="dt-action">
                                            <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-pencil"></use></svg>
                                            <span class="dt-card-only">Editar</span>
                                        </button>
                                        <form method="POST" action="{{ route('products.toggle-active', $product) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                aria-label="{{ $product->active ? 'Desactivar' : 'Activar' }}" title="{{ $product->active ? 'Desactivar' : 'Activar' }}"
                                                class="dt-action {{ $product->active ? 'dt-action--danger' : 'dt-action--success' }}">
                                                <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-{{ $product->active ? 'eye-off' : 'check' }}"></use></svg>
                                                <span class="dt-card-only">{{ $product->active ? 'Desactivar' : 'Activar' }}</span>
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
            @include('products.modals.create')
            @include('products.modals.edit')
            @include('products.modals.cost-history')
            @include('products.modals.bulk-category')

            <x-product-categories-modal
                name="product-categories"
                title="Categorías de artículos"
                :categories="$categories"
                :show="$showCategories"
                store-route="product-categories.store"
                update-route="product-categories.update"
                destroy-route="product-categories.destroy" />
        @endcan

    </div>
</x-app-layout>

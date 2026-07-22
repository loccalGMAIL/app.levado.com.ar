<x-app-layout>
    <x-slot name="title">Artículos</x-slot>

    @php
        $errorFields = ['name', 'type', 'recipe_id', 'product_category_id', 'unit', 'cost_per_unit', 'sku', 'barcode'];
        $errorsInCreate = $errors->hasAny($errorFields) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny($errorFields) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'type' => '', 'recipe_id' => '', 'product_category_id' => '', 'unit' => '', 'cost_per_unit' => '', 'sku' => '', 'barcode' => ''];
        $editingOnError = $errorsInEdit ? [
            'id'                  => old('product_id'),
            'name'                => old('name'),
            'type'                => old('type'),
            'recipe_id'           => old('recipe_id'),
            'product_category_id' => old('product_category_id'),
            'unit'                => old('unit'),
            'cost_per_unit'       => old('cost_per_unit'),
            'sku'                 => old('sku'),
            'barcode'             => old('barcode'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'product-edit');
            }
        }">

        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Artículos</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Productos que vendés: elaborados (desde una receta) y de reventa.</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
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
                </div>
            </div>

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
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
                <select name="status"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todos</option>
                    <option value="active"   @selected(request('status') === 'active')>Activos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('status') || request('type') || request('category'))
                    <a href="{{ route('products.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

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
                <x-responsive-table>
                    <x-slot:cards>
                    @foreach($products as $product)
                        @php
                            $baseCost = $product->isManufactured() ? $product->recipe?->unit_cost : $product->cost_per_unit;
                        @endphp
                        <div class="bg-white border border-miga rounded-lg p-4 shadow-sm {{ $product->active ? '' : 'opacity-50' }}">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium text-corteza">{{ $product->name }}</div>
                                    <div class="text-xs text-masa-madre mt-0.5">
                                        <x-product-type-badge :type="$product->type" />
                                        · {{ $product->unit->short() }}
                                        @if($product->category)
                                            · {{ $product->category->name }}
                                        @endif
                                        @if($product->isManufactured() && $product->recipe)
                                            · {{ $product->recipe->name }}
                                        @endif
                                    </div>
                                </div>
                                <x-status-badge :active="$product->active" />
                            </div>
                            <div class="mt-2 text-sm">
                                <span class="text-masa-madre text-xs">Costo base:</span>
                                <span class="font-mono text-corteza">
                                    {{ $baseCost !== null ? '$ '.number_format($baseCost, 2, ',', '.') : '—' }}
                                </span>
                            </div>
                            @if($product->sku || $product->barcode)
                                <div class="mt-1 text-xs text-masa-madre">
                                    {{ $product->sku ? 'SKU '.$product->sku : '' }}
                                    {{ $product->barcode ? ' · '.$product->barcode : '' }}
                                </div>
                            @endif
                            @can('manage-costs')
                                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-miga">
                                    <button type="button"
                                        @click="openEdit({{ Js::from([
                                            'id'            => $product->id,
                                            'name'          => $product->name,
                                            'type'          => $product->type->value,
                                            'recipe_id'     => $product->recipe_id ?? '',
                                            'product_category_id' => $product->product_category_id ?? '',
                                            'unit'          => $product->unit->value,
                                            'cost_per_unit' => $product->cost_per_unit !== null ? round((float) $product->cost_per_unit, 2) : '',
                                            'sku'           => $product->sku ?? '',
                                            'barcode'       => $product->barcode ?? '',
                                        ]) }})"
                                        class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                        Editar
                                    </button>
                                    <form method="POST" action="{{ route('products.toggle-active', $product) }}" class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="w-full py-1.5 px-3 text-sm rounded transition-colors {{ $product->active ? 'border border-red-300 text-red-600 hover:bg-red-50' : 'border border-green-300 text-green-600 hover:bg-green-50' }}">
                                            {{ $product->active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @endforeach
                    </x-slot:cards>

                    <thead class="bg-miga text-masa-madre border-b border-miga">
                        <tr>
                            <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                            <th class="px-4 py-3 font-medium">Tipo</th>
                            <th class="px-4 py-3 font-medium">Categoría</th>
                            <th class="px-4 py-3 font-medium">Origen</th>
                            <th class="px-4 py-3 font-medium">Unidad</th>
                            <th class="px-4 py-3 font-medium text-right">Costo base</th>
                            <th class="px-4 py-3 font-medium">Código</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            @can('manage-costs')
                                <th class="px-4 py-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($products as $product)
                            @php
                                $baseCost = $product->isManufactured() ? $product->recipe?->unit_cost : $product->cost_per_unit;
                            @endphp
                            <tr class="{{ $product->active ? '' : 'opacity-50' }}">
                                <td class="px-4 py-3 font-medium text-corteza">
                                    @can('manage-costs')
                                        <button type="button"
                                            @click="openEdit({{ Js::from([
                                                'id'            => $product->id,
                                                'name'          => $product->name,
                                                'type'          => $product->type->value,
                                                'recipe_id'     => $product->recipe_id ?? '',
                                                'product_category_id' => $product->product_category_id ?? '',
                                                'unit'          => $product->unit->value,
                                                'cost_per_unit' => $product->cost_per_unit !== null ? round((float) $product->cost_per_unit, 2) : '',
                                                'sku'           => $product->sku ?? '',
                                                'barcode'       => $product->barcode ?? '',
                                            ]) }})"
                                            class="hover:underline text-left">
                                            {{ $product->name }}
                                        </button>
                                    @else
                                        {{ $product->name }}
                                    @endcan
                                </td>
                                <td class="px-4 py-3">
                                    <x-product-type-badge :type="$product->type" />
                                </td>
                                <td class="px-4 py-3 text-masa-madre text-xs">
                                    @if($product->category)
                                        <span class="inline-flex items-center gap-1">
                                            {{ $product->category->name }}
                                            @unless($product->category->producible)
                                                <span class="text-[10px] text-gray-400" title="No se produce">·</span>
                                            @endunless
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-masa-madre text-xs">
                                    {{ $product->isManufactured() ? ($product->recipe?->name ?? '—') : 'Reventa' }}
                                </td>
                                <td class="px-4 py-3 text-masa-madre">
                                    {{ $product->unit->short() }}
                                </td>
                                <td class="px-4 py-3 text-right text-corteza font-mono">
                                    {{ $baseCost !== null ? number_format($baseCost, 2, ',', '.') : '—' }}
                                </td>
                                <td class="px-4 py-3 text-masa-madre text-xs font-mono">
                                    {{ $product->barcode ?? ($product->sku ?? '—') }}
                                </td>
                                <td class="px-4 py-3">
                                    <x-status-badge :active="$product->active" />
                                </td>
                                @can('manage-costs')
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <button type="button"
                                                @click="openEdit({{ Js::from([
                                                    'id'            => $product->id,
                                                    'name'          => $product->name,
                                                    'type'          => $product->type->value,
                                                    'recipe_id'     => $product->recipe_id ?? '',
                                                    'unit'          => $product->unit->value,
                                                    'cost_per_unit' => $product->cost_per_unit !== null ? round((float) $product->cost_per_unit, 2) : '',
                                                    'sku'           => $product->sku ?? '',
                                                    'barcode'       => $product->barcode ?? '',
                                                ]) }})"
                                                aria-label="Editar artículo" title="Editar artículo"
                                                class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                </svg>
                                            </button>
                                            <form method="POST" action="{{ route('products.toggle-active', $product) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    aria-label="{{ $product->active ? 'Desactivar' : 'Activar' }}" title="{{ $product->active ? 'Desactivar' : 'Activar' }}"
                                                    class="p-1.5 rounded transition-colors {{ $product->active ? 'text-red-500 hover:text-red-700 hover:bg-red-50' : 'text-green-600 hover:text-green-700 hover:bg-green-50' }}">
                                                    @if($product->active)
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                        </svg>
                                                    @else
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                    @endif
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>

                    <x-slot:footer>
                        @if($products->hasPages())
                            <div class="px-4 py-3 border-t border-miga">
                                {{ $products->links() }}
                            </div>
                        @endif
                    </x-slot:footer>
                </x-responsive-table>

                <p class="text-xs text-masa-madre">{{ $products->total() }} artículo(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('products.modals.create')
            @include('products.modals.edit')

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

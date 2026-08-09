<x-app-layout>
    <x-slot name="title">Ingredientes</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'brand', 'supplier_id', 'unit', 'cost_per_unit']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'brand', 'supplier_id', 'unit', 'cost_per_unit']) && old('_form') === 'edit';
        $supplierErrorsInCreate = $errors->hasAny(['name', 'phone', 'email', 'notes']) && old('_form') === 'supplier-quick-create';
        $editingDefault = ['id' => null, 'name' => '', 'brand' => '', 'supplier_id' => '', 'unit' => '', 'cost_per_unit' => '', 'cost_per_package' => null, 'subdivisions' => '', 'subdivision_label' => ''];
        $editingOnError = $errorsInEdit ? [
            'id'                => old('ingredient_id'),
            'name'              => old('name'),
            'brand'             => old('brand'),
            'supplier_id'       => old('supplier_id'),
            'unit'              => old('unit'),
            'cost_per_unit'     => old('cost_per_unit'),
            'cost_per_package'  => old('cost_per_package'),
            'subdivisions'      => old('subdivisions'),
            'subdivision_label' => old('subdivision_label'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                if (record.subdivisions && record.unit === 'u' && record.cost_per_package != null) {
                    record.cost_per_unit = record.cost_per_package;
                }
                this.editing = record;
                $dispatch('open-modal', 'ingredient-edit');
            }
        }">

        <div class="space-y-6">

            <x-list-header title="Ingredientes" subtitle="Materia prima con su costo por unidad.">
                @can('manage-costs')
                    <button type="button" id="btn-nuevo-ingrediente"
                        @click="$dispatch('open-modal', 'ingredient-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nuevo ingrediente
                    </button>
                @endcan
            </x-list-header>

            <x-list-filters :reset-route="route('ingredients.index')" />

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
            @endphp

            @if($ingredients->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('status'))
                        No se encontraron ingredientes con esos filtros.
                    @else
                        Todavía no hay ingredientes. Agregá el primero.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$ingredients" total-label="ingrediente">
                    <x-slot:head>
                        <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                        <th class="px-4 py-3 font-medium">Marca</th>
                        <th class="px-4 py-3 font-medium">Proveedor</th>
                        <th class="px-4 py-3 font-medium">Unidad</th>
                        <th class="px-4 py-3 font-medium text-right">Por envase</th>
                        <x-sortable-th column="cost_per_unit" :sort="$sort" :dir="$dir" align="right">Costo / sub-unidad</x-sortable-th>
                        <th class="px-4 py-3 font-medium text-right">Stock</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($ingredients as $ingredient)
                        @php
                            $stockLevel = $stockLevels->get($ingredient->id);
                            $stockQty = $stockLevel !== null ? (float) $stockLevel->quantity : 0.0;
                            $stockUnit = $ingredient->subdivisions && $ingredient->subdivision_label
                                ? $ingredient->subdivision_label
                                : $ingredient->unit->short();
                            $editPayload = [
                                'id'                => $ingredient->id,
                                'name'              => $ingredient->name,
                                'brand'             => $ingredient->brand ?? '',
                                'supplier_id'       => $ingredient->supplier_id ?? '',
                                'unit'              => $ingredient->unit->value,
                                'cost_per_unit'     => round((float) $ingredient->cost_per_unit, 2),
                                'cost_per_package'  => $ingredient->cost_per_package !== null ? round((float) $ingredient->cost_per_package, 2) : null,
                                'subdivisions'      => $ingredient->subdivisions ?? '',
                                'subdivision_label' => $ingredient->subdivision_label ?? '',
                            ];
                        @endphp
                        <x-data-table.row :dimmed="! $ingredient->active">
                            <x-data-table.cell role="title" class="font-medium text-corteza">
                                @can('manage-costs')
                                    <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                        class="hover:underline text-left">
                                        {{ $ingredient->name }}
                                    </button>
                                @else
                                    {{ $ingredient->name }}
                                @endcan
                            </x-data-table.cell>

                            <x-data-table.cell role="subtitle" class="text-masa-madre text-xs">{{ $ingredient->brand }}</x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre text-xs">{{ $ingredient->supplier?->name }}</x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre">{{ $ingredient->unit->short() }}</x-data-table.cell>

                            <x-data-table.cell role="meta" align="right" cards="hide" class="text-masa-madre font-mono text-xs">
                                @if($ingredient->cost_per_package !== null){{ number_format($ingredient->cost_per_package, 2, ',', '.') }}@endif
                            </x-data-table.cell>

                            <x-data-table.cell role="figure" align="right" class="text-corteza font-mono">
                                $ {{ number_format($ingredient->cost_per_unit, 2, ',', '.') }}
                                {{-- En la tabla el <th> ya dice «Costo / sub-unidad»: la unidad sólo
                                     se muestra ahí cuando hay subdivisión, que es cuando no se deduce. --}}
                                <span class="{{ $ingredient->subdivisions && $ingredient->subdivision_label ? '' : 'dt-card-only' }} text-xs text-masa-madre font-sans font-normal">/ {{ $stockUnit }}</span>
                            </x-data-table.cell>

                            <x-data-table.cell role="meta" align="right" label="Stock:" class="font-mono"
                                x-data="stockCell({{ Js::from([
                                    'value' => $stockQty,
                                    'valueFormatted' => number_format($stockQty, 2, ',', '.'),
                                    'updateUrl' => route('stock.level.update', ['ingredient', $ingredient->id]),
                                ]) }})">
                                <span class="inline-flex items-center gap-1.5">
                                    @can('manage-costs')
                                        <span x-show="!editing && !saving"
                                            @click="startEdit()"
                                            class="cursor-pointer hover:text-horno select-none"
                                            :class="value < 0 ? 'text-red-600' : 'text-corteza'"
                                            x-text="valueFormatted"></span>
                                        <input
                                            x-show="editing"
                                            x-ref="input"
                                            type="number" step="0.01" min="0"
                                            @input="isDirty = true"
                                            @keydown.enter.prevent="save()"
                                            @keydown.escape="editing = false; isDirty = false"
                                            @blur="save()"
                                            class="w-24 text-right text-sm border-gray-300 rounded px-1 py-0.5 focus:border-horno focus:ring-horno font-mono">
                                        <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                    @else
                                        <span :class="value < 0 ? 'text-red-600' : 'text-corteza'" x-text="valueFormatted"></span>
                                    @endcan
                                    <span class="text-masa-madre text-xs font-sans">{{ $stockUnit }}</span>
                                    <a href="{{ route('stock.show', ['ingredient', $ingredient->id]) }}"
                                        class="inline-flex items-center gap-1 text-masa-madre hover:text-corteza"
                                        title="Ver movimientos de stock">
                                        <x-icon name="clock" class="w-3.5 h-3.5" />
                                        <span class="dt-card-only text-xs font-sans underline">historial</span>
                                    </a>
                                </span>
                            </x-data-table.cell>

                            <x-data-table.cell role="badge">
                                <x-status-badge :active="$ingredient->active" />
                            </x-data-table.cell>

                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions">
                                        <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                            aria-label="Editar ingrediente" title="Editar ingrediente"
                                            class="dt-action">
                                            <x-icon name="pencil" />
                                            <span class="dt-card-only">Editar</span>
                                        </button>
                                        <form method="POST" action="{{ route('ingredients.toggle-active', $ingredient) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                aria-label="{{ $ingredient->active ? 'Desactivar' : 'Activar' }}"
                                                title="{{ $ingredient->active ? 'Desactivar' : 'Activar' }}"
                                                class="dt-action {{ $ingredient->active ? 'dt-action--danger' : 'dt-action--success' }}">
                                                <x-icon :name="$ingredient->active ? 'eye-off' : 'eye'" />
                                                <span class="dt-card-only">{{ $ingredient->active ? 'Desactivar' : 'Activar' }}</span>
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
            @include('ingredients.modals.create')
            @include('ingredients.modals.edit')
            @include('suppliers.modals.quick-create')
        @endcan

    </div>
</x-app-layout>

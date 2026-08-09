<x-app-layout>
    <x-slot name="title">Envases</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'brand', 'supplier_id', 'cost_per_unit', 'subdivisions', 'subdivision_label']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'brand', 'supplier_id', 'cost_per_unit', 'subdivisions', 'subdivision_label']) && old('_form') === 'edit';
        $supplierErrorsInCreate = $errors->hasAny(['name', 'phone', 'email', 'notes']) && old('_form') === 'supplier-quick-create';
        $editingDefault = ['id' => null, 'name' => '', 'brand' => '', 'supplier_id' => '', 'cost_per_unit' => '', 'cost_per_package' => null, 'subdivisions' => null, 'subdivision_label' => ''];
        $editingOnError = $errorsInEdit ? [
            'id'                => old('packaging_id'),
            'name'              => old('name'),
            'brand'             => old('brand'),
            'supplier_id'       => old('supplier_id'),
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
                if (record.subdivisions && record.cost_per_package != null) {
                    record.cost_per_unit = record.cost_per_package;
                }
                this.editing = record;
                $dispatch('open-modal', 'packaging-edit');
            }
        }">

        <div class="space-y-6">

            <x-list-header title="Envases"
                subtitle="Cajas, bolsas y materiales de presentación con su costo por unidad.">
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'packaging-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nuevo envase
                    </button>
                @endcan
            </x-list-header>

            <x-list-filters :reset-route="route('packaging.index')" />

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
            @endphp

            @if($packagings->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('status'))
                        No se encontraron envases con esos filtros.
                    @else
                        Todavía no hay envases. Agregá el primero.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$packagings" total-label="envase">
                    <x-slot:head>
                        <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                        <th class="px-4 py-3 font-medium">Marca</th>
                        <th class="px-4 py-3 font-medium">Proveedor</th>
                        <th class="px-4 py-3 font-medium text-right">Por presentación</th>
                        <x-sortable-th column="cost_per_unit" :sort="$sort" :dir="$dir" align="right">Costo / sub-unidad</x-sortable-th>
                        <th class="px-4 py-3 font-medium text-right">Stock</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($packagings as $packaging)
                        @php
                            $stockLevel = $stockLevels->get($packaging->id);
                            $stockQty = $stockLevel !== null ? (float) $stockLevel->quantity : 0.0;
                            $stockUnit = $packaging->subdivisions && $packaging->subdivision_label
                                ? $packaging->subdivision_label
                                : 'u.';
                            $editPayload = [
                                'id'                => $packaging->id,
                                'name'              => $packaging->name,
                                'brand'             => $packaging->brand ?? '',
                                'supplier_id'       => $packaging->supplier_id ?? '',
                                'cost_per_unit'     => round((float) $packaging->cost_per_unit, 2),
                                'cost_per_package'  => $packaging->cost_per_package !== null ? round((float) $packaging->cost_per_package, 2) : null,
                                'subdivisions'      => $packaging->subdivisions,
                                'subdivision_label' => $packaging->subdivision_label ?? '',
                            ];
                        @endphp
                        <x-data-table.row :dimmed="! $packaging->active">
                            <x-data-table.cell role="title" class="font-medium text-corteza">
                                @can('manage-costs')
                                    <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                        class="hover:underline text-left">
                                        {{ $packaging->name }}
                                    </button>
                                @else
                                    {{ $packaging->name }}
                                @endcan
                                @if($packaging->subdivisions)
                                    <span class="text-xs text-masa-madre block font-normal">
                                        {{ $packaging->subdivisions }} {{ $packaging->subdivision_label ?? 'u.' }} / presentación
                                    </span>
                                @endif
                            </x-data-table.cell>

                            <x-data-table.cell role="subtitle" class="text-masa-madre text-xs">{{ $packaging->brand }}</x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre text-xs">{{ $packaging->supplier?->name }}</x-data-table.cell>

                            <x-data-table.cell role="meta" align="right" cards="hide" class="text-masa-madre font-mono text-xs">
                                @if($packaging->cost_per_package !== null){{ number_format($packaging->cost_per_package, 2, ',', '.') }}@endif
                            </x-data-table.cell>

                            <x-data-table.cell role="figure" align="right" class="font-mono text-corteza"
                                x-data="costCell({{ Js::from([
                                    'value' => (float) $packaging->cost_per_unit,
                                    'valueFormatted' => number_format($packaging->cost_per_unit, 2, ',', '.'),
                                    'updateUrl' => route('packaging.cost.update', $packaging),
                                ]) }})">
                                @can('manage-costs')
                                    <span x-show="!editing && !saving"
                                        @click="startEdit()"
                                        class="cursor-pointer hover:text-horno select-none"
                                        x-text="'$ ' + valueFormatted"></span>
                                    <input x-show="editing" x-ref="input"
                                        type="number" step="0.01" min="0"
                                        @input="isDirty = true"
                                        @keydown.enter.prevent="save()"
                                        @keydown.escape="editing = false; isDirty = false"
                                        @blur="save()"
                                        class="w-28 text-right text-sm border-gray-300 rounded px-1 py-0.5 focus:border-horno focus:ring-horno font-mono">
                                    <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                @else
                                    $ <span x-text="valueFormatted"></span>
                                @endcan
                                <span class="dt-card-only text-xs text-masa-madre font-sans font-normal">/ {{ $stockUnit }}</span>
                            </x-data-table.cell>

                            <x-data-table.cell role="meta" align="right" label="Stock:" class="font-mono"
                                x-data="stockCell({{ Js::from([
                                    'value' => $stockQty,
                                    'valueFormatted' => number_format($stockQty, 2, ',', '.'),
                                    'updateUrl' => route('stock.level.update', ['packaging', $packaging->id]),
                                ]) }})">
                                <span class="inline-flex items-center gap-1.5">
                                    @can('manage-costs')
                                        <span x-show="!editing && !saving"
                                            @click="startEdit()"
                                            class="cursor-pointer hover:text-horno select-none"
                                            :class="value < 0 ? 'text-red-600' : 'text-corteza'"
                                            x-text="valueFormatted"></span>
                                        <input x-show="editing" x-ref="input"
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
                                    <a href="{{ route('stock.show', ['packaging', $packaging->id]) }}"
                                        class="inline-flex items-center gap-1 text-masa-madre hover:text-corteza"
                                        title="Ver movimientos de stock">
                                        <x-icon name="clock" class="w-3.5 h-3.5" />
                                        <span class="dt-card-only text-xs font-sans underline">historial</span>
                                    </a>
                                </span>
                            </x-data-table.cell>

                            <x-data-table.cell role="badge">
                                <x-status-badge :active="$packaging->active" />
                            </x-data-table.cell>

                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions">
                                        <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                            aria-label="Editar envase" title="Editar envase" class="dt-action">
                                            <x-icon name="pencil" />
                                            <span class="dt-card-only">Editar</span>
                                        </button>
                                        <form method="POST" action="{{ route('packaging.toggle-active', $packaging) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                aria-label="{{ $packaging->active ? 'Desactivar' : 'Activar' }}"
                                                title="{{ $packaging->active ? 'Desactivar' : 'Activar' }}"
                                                class="dt-action {{ $packaging->active ? 'dt-action--danger' : 'dt-action--success' }}">
                                                <x-icon :name="$packaging->active ? 'eye-off' : 'eye'" />
                                                <span class="dt-card-only">{{ $packaging->active ? 'Desactivar' : 'Activar' }}</span>
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
            @include('packaging.modals.create')
            @include('packaging.modals.edit')
            @include('suppliers.modals.quick-create')
        @endcan

    </div>
</x-app-layout>

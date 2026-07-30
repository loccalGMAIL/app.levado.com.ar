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
            mobileExpanded: false,
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

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Ingredientes</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Materia prima con su costo por unidad.</p>
                </div>
                @can('manage-costs')
                    <button type="button" id="btn-nuevo-ingrediente"
                        @click="$dispatch('open-modal', 'ingredient-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nuevo ingrediente
                    </button>
                @endcan
            </div>

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <select name="status"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todos</option>
                    <option value="active"   @selected(request('status') === 'active')>Activos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('ingredients.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

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
                                <x-responsive-table>
                    <x-slot:cards>
                    @foreach($ingredients as $ingredient)
                        @php
                            $stockLevel = $stockLevels->get($ingredient->id);
                            $stockQty = $stockLevel !== null ? (float) $stockLevel->quantity : 0.0;
                            $stockUnit = $ingredient->subdivisions && $ingredient->subdivision_label
                                ? $ingredient->subdivision_label
                                : $ingredient->unit->short();
                        @endphp
                        <div class="bg-white border border-miga rounded-lg p-4 shadow-sm {{ $ingredient->active ? '' : 'opacity-50' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-medium text-corteza">{{ $ingredient->name }}</div>
                                    <div class="text-xs text-masa-madre mt-0.5">
                                        {{ $ingredient->unit->short() }}
                                        @if($ingredient->brand || $ingredient->supplier)
                                            · {{ implode(', ', array_filter([$ingredient->brand, $ingredient->supplier?->name])) }}
                                        @endif
                                    </div>
                                </div>
                                <x-status-badge :active="$ingredient->active" />
                            </div>
                            <div class="mt-2 [overflow-wrap:anywhere]">
                                <span class="text-sm font-mono text-corteza">$ {{ number_format($ingredient->cost_per_unit, 2, ',', '.') }}</span>
                                <span class="text-xs text-masa-madre">/ {{ $ingredient->subdivisions && $ingredient->subdivision_label ? $ingredient->subdivision_label : $ingredient->unit->short() }}</span>
                            </div>
                            <div class="mt-1 text-xs text-masa-madre">
                                Stock:
                                <span class="font-mono {{ $stockQty < 0 ? 'text-red-600' : 'text-corteza' }}">{{ number_format($stockQty, 2, ',', '.') }}</span>
                                {{ $stockUnit }}
                                <a href="{{ route('stock.show', ['ingredient', $ingredient->id]) }}" class="hover:text-corteza underline">historial</a>
                            </div>
                            @can('manage-costs')
                                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-miga">
                                    <button type="button"
                                        @click="openEdit({{ Js::from([
                                            'id'                => $ingredient->id,
                                            'name'              => $ingredient->name,
                                            'brand'             => $ingredient->brand ?? '',
                                            'supplier_id'       => $ingredient->supplier_id ?? '',
                                            'unit'              => $ingredient->unit->value,
                                            'cost_per_unit'     => round((float) $ingredient->cost_per_unit, 2),
                                            'cost_per_package'  => $ingredient->cost_per_package !== null ? round((float) $ingredient->cost_per_package, 2) : null,
                                            'subdivisions'      => $ingredient->subdivisions ?? '',
                                            'subdivision_label' => $ingredient->subdivision_label ?? '',
                                        ]) }})"
                                        class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                        Editar
                                    </button>
                                    <form method="POST" action="{{ route('ingredients.toggle-active', $ingredient) }}" class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="w-full py-1.5 px-3 text-sm rounded transition-colors {{ $ingredient->active ? 'border border-red-300 text-red-600 hover:bg-red-50' : 'border border-green-300 text-green-600 hover:bg-green-50' }}">
                                            {{ $ingredient->active ? 'Desactivar' : 'Activar' }}
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
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($ingredients as $ingredient)
                                @php
                                    $stockLevel = $stockLevels->get($ingredient->id);
                                    $stockQty = $stockLevel !== null ? (float) $stockLevel->quantity : 0.0;
                                    $stockUnit = $ingredient->subdivisions && $ingredient->subdivision_label
                                        ? $ingredient->subdivision_label
                                        : $ingredient->unit->short();
                                @endphp
                                <tr class="{{ $ingredient->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        @can('manage-costs')
                                            <button type="button"
                                                @click="openEdit({{ Js::from([
                                                    'id'                => $ingredient->id,
                                                    'name'              => $ingredient->name,
                                                    'brand'             => $ingredient->brand ?? '',
                                                    'supplier_id'       => $ingredient->supplier_id ?? '',
                                                    'unit'              => $ingredient->unit->value,
                                                    'cost_per_unit'     => round((float) $ingredient->cost_per_unit, 2),
                                                    'cost_per_package'  => $ingredient->cost_per_package !== null ? round((float) $ingredient->cost_per_package, 2) : null,
                                                    'subdivisions'      => $ingredient->subdivisions ?? '',
                                                    'subdivision_label' => $ingredient->subdivision_label ?? '',
                                                ]) }})"
                                                class="hover:underline text-left">
                                                {{ $ingredient->name }}
                                            </button>
                                        @else
                                            {{ $ingredient->name }}
                                        @endcan
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">
                                        {{ $ingredient->brand ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">
                                        {{ $ingredient->supplier?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre">
                                        {{ $ingredient->unit->short() }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-masa-madre font-mono text-xs">
                                        @if($ingredient->cost_per_package !== null)
                                            {{ number_format($ingredient->cost_per_package, 2, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-corteza font-mono">
                                        {{ number_format($ingredient->cost_per_unit, 2, ',', '.') }}
                                        @if($ingredient->subdivisions && $ingredient->subdivision_label)
                                            <span class="block text-xs text-masa-madre font-normal">/ {{ $ingredient->subdivision_label }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono"
                                        x-data="{
                                            editing: false,
                                            saving: false,
                                            isDirty: false,
                                            qty: {{ $stockQty }},
                                            qtyFormatted: '{{ number_format($stockQty, 2, ',', '.') }}',
                                            startEdit() {
                                                this.isDirty = false;
                                                this.$refs.stockInput.value = parseFloat(this.qty).toFixed(2);
                                                this.editing = true;
                                                this.$nextTick(() => this.$refs.stockInput.select());
                                            },
                                            async saveStock() {
                                                if (this.saving) return;
                                                if (!this.isDirty) { this.editing = false; return; }
                                                const raw = this.$refs.stockInput.value.trim();
                                                if (raw === '') { this.editing = false; return; }
                                                this.saving = true;
                                                this.editing = false;
                                                try {
                                                    const res = await fetch('{{ route('stock.level.update', ['ingredient', $ingredient->id]) }}', {
                                                        method: 'PATCH',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                            'Accept': 'application/json',
                                                        },
                                                        body: JSON.stringify({ counted_quantity: raw })
                                                    });
                                                    const data = await res.json();
                                                    this.qty = data.quantity;
                                                    this.qtyFormatted = data.quantity_formatted;
                                                } finally {
                                                    this.saving = false;
                                                }
                                            }
                                        }">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            @can('manage-costs')
                                                <div x-show="!editing && !saving"
                                                    @click="startEdit()"
                                                    class="cursor-pointer hover:text-horno select-none"
                                                    :class="qty < 0 ? 'text-red-600' : 'text-corteza'"
                                                    x-text="qtyFormatted"></div>
                                                <input
                                                    x-show="editing"
                                                    x-ref="stockInput"
                                                    type="number" step="0.01" min="0"
                                                    @input="isDirty = true"
                                                    @keydown.enter.prevent="saveStock()"
                                                    @keydown.escape="editing = false; isDirty = false"
                                                    @blur="saveStock()"
                                                    class="w-24 text-right text-sm border-gray-300 rounded px-1 py-0.5 focus:border-horno focus:ring-horno font-mono">
                                                <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                            @else
                                                <span :class="qty < 0 ? 'text-red-600' : 'text-corteza'" x-text="qtyFormatted"></span>
                                            @endcan
                                            <span class="text-masa-madre text-xs">{{ $stockUnit }}</span>
                                            <a href="{{ route('stock.show', ['ingredient', $ingredient->id]) }}"
                                                class="text-masa-madre hover:text-corteza" title="Ver movimientos de stock">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-status-badge :active="$ingredient->active" />
                                    </td>
                                    @can('manage-costs')
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button"
                                                    @click="openEdit({{ Js::from([
                                                        'id'                => $ingredient->id,
                                                        'name'              => $ingredient->name,
                                                        'brand'             => $ingredient->brand ?? '',
                                                        'supplier_id'       => $ingredient->supplier_id ?? '',
                                                        'unit'              => $ingredient->unit->value,
                                                        'cost_per_unit'     => round((float) $ingredient->cost_per_unit, 2),
                                                        'cost_per_package'  => $ingredient->cost_per_package !== null ? round((float) $ingredient->cost_per_package, 2) : null,
                                                        'subdivisions'      => $ingredient->subdivisions ?? '',
                                                        'subdivision_label' => $ingredient->subdivision_label ?? '',
                                                    ]) }})"
                                                    aria-label="Editar ingrediente" title="Editar ingrediente"
                                                    class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                    </svg>
                                                </button>
                                                <form method="POST" action="{{ route('ingredients.toggle-active', $ingredient) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        aria-label="{{ $ingredient->active ? 'Desactivar' : 'Activar' }}" title="{{ $ingredient->active ? 'Desactivar' : 'Activar' }}"
                                                        class="p-1.5 rounded transition-colors {{ $ingredient->active ? 'text-red-500 hover:text-red-700 hover:bg-red-50' : 'text-green-600 hover:text-green-700 hover:bg-green-50' }}">
                                                        @if($ingredient->active)
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
                        @if($ingredients->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $ingredients->links() }}
                        </div>
                    @endif
                    </x-slot:footer>
                </x-responsive-table>

                <p class="text-xs text-masa-madre">{{ $ingredients->total() }} ingrediente(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('ingredients.modals.create')
            @include('ingredients.modals.edit')
            @include('suppliers.modals.quick-create')
        @endcan

    </div>
</x-app-layout>

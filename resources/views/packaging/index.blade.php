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
            mobileExpanded: false,
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                if (record.subdivisions && record.cost_per_package !== null) {
                    record.cost_per_unit = record.cost_per_package;
                }
                this.editing = record;
                $dispatch('open-modal', 'packaging-edit');
            }
        }">

        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Envases</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Cajas, bolsas y materiales de presentación con su costo por unidad.</p>
                </div>
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'packaging-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nuevo envase
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
                    <a href="{{ route('packaging.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
                $sortUrl = fn (string $col): string => request()->url() . '?' . http_build_query(
                    array_merge(request()->except(['sort', 'dir', 'page']), [
                        'sort' => $col,
                        'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
                    ])
                );
                $sortIcon = fn (string $col): string => $sort === $col ? ($dir === 'asc' ? '↑' : '↓') : '';
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
                {{-- Cards (mobile) --}}
                <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="space-y-3">
                    @foreach($packagings as $packaging)
                        <div class="bg-white border border-miga rounded-lg p-4 shadow-sm {{ $packaging->active ? '' : 'opacity-50' }}"
                            x-data="{
                                editing: false,
                                saving: false,
                                isDirty: false,
                                cost: {{ (float) $packaging->cost_per_unit }},
                                costFormatted: '{{ number_format($packaging->cost_per_unit, 2, ',', '.') }}',
                                startEdit() {
                                    this.isDirty = false;
                                    this.$refs.costInputCard.value = parseFloat(this.cost).toFixed(2);
                                    this.editing = true;
                                    this.$nextTick(() => this.$refs.costInputCard.select());
                                },
                                async saveCost() {
                                    if (this.saving) return;
                                    if (!this.isDirty) { this.editing = false; return; }
                                    const raw = this.$refs.costInputCard.value.trim();
                                    if (raw === '') { this.editing = false; return; }
                                    this.saving = true;
                                    this.editing = false;
                                    try {
                                        const res = await fetch('{{ route('packaging.cost.update', $packaging) }}', {
                                            method: 'PATCH',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                'Accept': 'application/json',
                                            },
                                            body: JSON.stringify({ cost_per_unit: raw })
                                        });
                                        const data = await res.json();
                                        this.cost = data.cost_per_unit;
                                        this.costFormatted = data.cost_per_unit_formatted;
                                    } finally {
                                        this.saving = false;
                                    }
                                }
                            }">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium text-corteza">{{ $packaging->name }}</div>
                                    <div class="text-xs text-masa-madre mt-0.5">
                                        @if($packaging->brand || $packaging->supplier)
                                            {{ implode(', ', array_filter([$packaging->brand, $packaging->supplier?->name])) }}
                                        @endif
                                        @if($packaging->subdivisions)
                                            · {{ $packaging->subdivisions }} {{ $packaging->subdivision_label ?? 'u.' }} / presentación
                                        @endif
                                    </div>
                                </div>
                                <x-status-badge :active="$packaging->active" />
                            </div>
                            <div class="mt-2 font-mono text-corteza text-sm">
                                @can('manage-costs')
                                    <div x-show="!editing && !saving"
                                        @click="startEdit()"
                                        class="cursor-pointer hover:text-horno select-none inline-flex items-center gap-1">
                                        $ <span x-text="costFormatted"></span>
                                        <span class="text-xs text-masa-madre font-normal">/ {{ $packaging->subdivisions && $packaging->subdivision_label ? $packaging->subdivision_label : 'u.' }}</span>
                                        <svg class="w-3 h-3 text-masa-madre" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6-6 3 3-6 6H9v-3z"/></svg>
                                    </div>
                                    <input
                                        x-show="editing"
                                        x-ref="costInputCard"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        @input="isDirty = true"
                                        @keydown.enter.prevent="saveCost()"
                                        @keydown.escape="editing = false; isDirty = false"
                                        @blur="saveCost()"
                                        class="w-32 text-right text-sm border-gray-300 rounded px-1 py-0.5 focus:border-horno focus:ring-horno font-mono">
                                    <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                @else
                                    $ <span>{{ number_format($packaging->cost_per_unit, 2, ',', '.') }}</span>
                                @endcan
                            </div>
                            @can('manage-costs')
                                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-miga">
                                    <button type="button"
                                        @click="openEdit({{ Js::from([
                                            'id'               => $packaging->id,
                                            'name'             => $packaging->name,
                                            'brand'            => $packaging->brand ?? '',
                                            'supplier_id'      => $packaging->supplier_id ?? '',
                                            'cost_per_unit'    => $packaging->cost_per_unit,
                                            'cost_per_package' => $packaging->cost_per_package,
                                            'subdivisions'     => $packaging->subdivisions,
                                            'subdivision_label' => $packaging->subdivision_label ?? '',
                                        ]) }})"
                                        class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                        Editar
                                    </button>
                                    <form method="POST" action="{{ route('packaging.toggle-active', $packaging) }}" class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="w-full py-1.5 px-3 text-sm rounded transition-colors {{ $packaging->active ? 'border border-red-300 text-red-600 hover:bg-red-50' : 'border border-green-300 text-green-600 hover:bg-green-50' }}">
                                            {{ $packaging->active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @endforeach
                    <button type="button" @click="mobileExpanded = true"
                        class="w-full py-2 text-sm text-masa-madre hover:text-corteza text-center">
                        Ver tabla completa ↓
                    </button>
                </div>

                {{-- Tabla (desktop siempre, mobile si está expandida) --}}
                <div :class="mobileExpanded ? '' : 'hidden md:block'" class="bg-white rounded-lg shadow overflow-x-auto">
                    <div class="md:hidden px-4 py-2 border-b border-miga">
                        <button type="button" @click="mobileExpanded = false"
                            class="text-sm text-masa-madre hover:text-corteza">
                            ← Volver a cards
                        </button>
                    </div>
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    <a href="{{ $sortUrl('name') }}" class="hover:text-corteza inline-flex items-center gap-1">
                                        Nombre <span class="text-xs">{{ $sortIcon('name') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-medium">Marca</th>
                                <th class="px-4 py-3 font-medium">Proveedor</th>
                                <th class="px-4 py-3 font-medium text-right">Por presentación</th>
                                <th class="px-4 py-3 font-medium text-right">
                                    <a href="{{ $sortUrl('cost_per_unit') }}" class="hover:text-corteza inline-flex items-center justify-end gap-1">
                                        Costo / sub-unidad <span class="text-xs">{{ $sortIcon('cost_per_unit') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                @can('manage-costs')
                                    <th class="px-4 py-3"></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($packagings as $packaging)
                                <tr class="{{ $packaging->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        @can('manage-costs')
                                            <button type="button"
                                                @click="openEdit({{ Js::from([
                                                    'id'                => $packaging->id,
                                                    'name'              => $packaging->name,
                                                    'brand'             => $packaging->brand ?? '',
                                                    'supplier_id'       => $packaging->supplier_id ?? '',
                                                    'cost_per_unit'     => $packaging->cost_per_unit,
                                                    'cost_per_package'  => $packaging->cost_per_package,
                                                    'subdivisions'      => $packaging->subdivisions,
                                                    'subdivision_label' => $packaging->subdivision_label ?? '',
                                                ]) }})"
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
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">{{ $packaging->brand ?? '—' }}</td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">{{ $packaging->supplier?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-masa-madre font-mono text-xs">
                                        @if($packaging->cost_per_package !== null)
                                            {{ number_format($packaging->cost_per_package, 2, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-corteza"
                                        x-data="{
                                            editing: false,
                                            saving: false,
                                            isDirty: false,
                                            cost: {{ (float) $packaging->cost_per_unit }},
                                            costFormatted: '{{ number_format($packaging->cost_per_unit, 2, ',', '.') }}',
                                            startEdit() {
                                                this.isDirty = false;
                                                this.$refs.costInput.value = parseFloat(this.cost).toFixed(2);
                                                this.editing = true;
                                                this.$nextTick(() => this.$refs.costInput.select());
                                            },
                                            async saveCost() {
                                                if (this.saving) return;
                                                if (!this.isDirty) { this.editing = false; return; }
                                                const raw = this.$refs.costInput.value.trim();
                                                if (raw === '') { this.editing = false; return; }
                                                this.saving = true;
                                                this.editing = false;
                                                try {
                                                    const res = await fetch('{{ route('packaging.cost.update', $packaging) }}', {
                                                        method: 'PATCH',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                                            'Accept': 'application/json',
                                                        },
                                                        body: JSON.stringify({ cost_per_unit: raw })
                                                    });
                                                    const data = await res.json();
                                                    this.cost = data.cost_per_unit;
                                                    this.costFormatted = data.cost_per_unit_formatted;
                                                } finally {
                                                    this.saving = false;
                                                }
                                            }
                                        }">
                                        @can('manage-costs')
                                            <div x-show="!editing && !saving"
                                                @click="startEdit()"
                                                class="cursor-pointer hover:text-horno select-none"
                                                x-text="'$ ' + costFormatted"></div>
                                            <input
                                                x-show="editing"
                                                x-ref="costInput"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                @input="isDirty = true"
                                                @keydown.enter.prevent="saveCost()"
                                                @keydown.escape="editing = false; isDirty = false"
                                                @blur="saveCost()"
                                                class="w-28 text-right text-sm border-gray-300 rounded px-1 py-0.5 focus:border-horno focus:ring-horno font-mono">
                                            <span x-show="saving" class="text-xs text-masa-madre">guardando…</span>
                                        @else
                                            $ <span x-text="costFormatted"></span>
                                        @endcan
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-status-badge :active="$packaging->active" />
                                    </td>
                                    @can('manage-costs')
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button"
                                                    @click="openEdit({{ Js::from([
                                                        'id'               => $packaging->id,
                                                        'name'             => $packaging->name,
                                                        'brand'            => $packaging->brand ?? '',
                                                        'supplier_id'      => $packaging->supplier_id ?? '',
                                                        'cost_per_unit'    => $packaging->cost_per_unit,
                                                        'subdivisions'     => $packaging->subdivisions,
                                                        'subdivision_label' => $packaging->subdivision_label ?? '',
                                                    ]) }})"
                                                    aria-label="Editar envase" title="Editar envase"
                                                    class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                    </svg>
                                                </button>
                                                <form method="POST" action="{{ route('packaging.toggle-active', $packaging) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        aria-label="{{ $packaging->active ? 'Desactivar' : 'Activar' }}" title="{{ $packaging->active ? 'Desactivar' : 'Activar' }}"
                                                        class="p-1.5 rounded transition-colors {{ $packaging->active ? 'text-red-500 hover:text-red-700 hover:bg-red-50' : 'text-green-600 hover:text-green-700 hover:bg-green-50' }}">
                                                        @if($packaging->active)
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
                    </table>

                    @if($packagings->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $packagings->links() }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-masa-madre">{{ $packagings->total() }} envase(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('packaging.modals.create')
            @include('packaging.modals.edit')
            @include('suppliers.modals.quick-create')
        @endcan

    </div>
</x-app-layout>

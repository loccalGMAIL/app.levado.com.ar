<x-app-layout>
    <x-slot name="title">Proveedores</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'phone', 'email', 'notes']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'phone', 'email', 'notes']) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'phone' => '', 'email' => '', 'notes' => ''];
        $editingOnError = $errorsInEdit ? [
            'id'    => old('supplier_id'),
            'name'  => old('name'),
            'phone' => old('phone'),
            'email' => old('email'),
            'notes' => old('notes'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'supplier-edit');
            }
        }">

        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Proveedores</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Empresas y personas de quienes comprás insumos.</p>
                </div>
                @can('manage-costs')
                    <button type="button" id="btn-nuevo-proveedor"
                        @click="$dispatch('open-modal', 'supplier-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nuevo proveedor
                    </button>
                @endcan
            </div>

            <form method="GET" class="flex gap-3 items-end flex-wrap">
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
                    <a href="{{ route('suppliers.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @if(!$suppliers->isEmpty())
                @php
                    $sort = request('sort', 'name');
                    $dir  = request('dir', 'asc');
                    $nextDir = ($sort === 'name' && $dir === 'asc') ? 'desc' : 'asc';
                    $sortNameUrl = request()->url() . '?' . http_build_query(
                        array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'name', 'dir' => $nextDir])
                    );
                @endphp
                <div class="flex items-center gap-2 text-xs text-masa-madre">
                    <span>Ordenar por:</span>
                    <a href="{{ $sortNameUrl }}" class="hover:text-corteza hover:underline">
                        Nombre
                        @if($sort === 'name')
                            <span>{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </a>
                </div>
            @endif

            @if($suppliers->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('status'))
                        No se encontraron proveedores con esos filtros.
                    @else
                        Todavía no hay proveedores. Agregá el primero.
                    @endif
                </x-empty-state>
            @else
                <div class="space-y-3">
                    @foreach($suppliers as $supplier)
                        <div class="bg-white rounded-lg shadow px-5 py-4 flex items-start justify-between gap-4 {{ $supplier->active ? '' : 'opacity-50' }}">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    @can('manage-costs')
                                        <button type="button"
                                            @click="openEdit({{ Js::from([
                                                'id'    => $supplier->id,
                                                'name'  => $supplier->name,
                                                'phone' => $supplier->phone ?? '',
                                                'email' => $supplier->email ?? '',
                                                'notes' => $supplier->notes ?? '',
                                            ]) }})"
                                            class="font-medium text-corteza text-sm hover:underline text-left">
                                            {{ $supplier->name }}
                                        </button>
                                    @else
                                        <span class="font-medium text-corteza text-sm">{{ $supplier->name }}</span>
                                    @endcan
                                    @if(!$supplier->active)
                                        <x-status-badge :active="false" />
                                    @endif
                                </div>
                                @if($supplier->phone || $supplier->email)
                                    <p class="text-xs text-masa-madre mt-0.5">
                                        {{ collect([$supplier->phone, $supplier->email])->filter()->implode(' · ') }}
                                    </p>
                                @endif
                                @if($supplier->notes)
                                    <p class="text-xs text-masa-madre mt-0.5 truncate max-w-sm">{{ $supplier->notes }}</p>
                                @endif
                            </div>
                            @can('manage-costs')
                                <div class="flex items-center gap-1 shrink-0">
                                    <button type="button"
                                        @click="openEdit({{ Js::from([
                                            'id'    => $supplier->id,
                                            'name'  => $supplier->name,
                                            'phone' => $supplier->phone ?? '',
                                            'email' => $supplier->email ?? '',
                                            'notes' => $supplier->notes ?? '',
                                        ]) }})"
                                        aria-label="Editar proveedor" title="Editar proveedor"
                                        class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                        </svg>
                                    </button>
                                    <form method="POST" action="{{ route('suppliers.toggle-active', $supplier) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            aria-label="{{ $supplier->active ? 'Desactivar' : 'Activar' }}" title="{{ $supplier->active ? 'Desactivar' : 'Activar' }}"
                                            class="p-1.5 rounded transition-colors {{ $supplier->active ? 'text-masa-madre hover:text-red-600 hover:bg-red-50' : 'text-green-600 hover:text-green-700 hover:bg-green-50' }}">
                                            @if($supplier->active)
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
                            @endcan
                        </div>
                    @endforeach
                </div>

                @if($suppliers->hasPages())
                    <div class="bg-white rounded-lg shadow px-4 py-3">
                        {{ $suppliers->links() }}
                    </div>
                @endif

                <p class="text-xs text-masa-madre">{{ $suppliers->total() }} proveedor(es) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('suppliers.modals.create')
            @include('suppliers.modals.edit')
        @endcan

    </div>
</x-app-layout>

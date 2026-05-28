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

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

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

            @if($suppliers->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    @if(request('search') || request('status'))
                        No se encontraron proveedores con esos filtros.
                    @else
                        Todavía no hay proveedores. Agregá el primero.
                    @endif
                </div>
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
                                        <span class="text-[11px] text-gray-400">inactivo</span>
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
                                <div class="flex items-center gap-3 shrink-0">
                                    <button type="button"
                                        @click="openEdit({{ Js::from([
                                            'id'    => $supplier->id,
                                            'name'  => $supplier->name,
                                            'phone' => $supplier->phone ?? '',
                                            'email' => $supplier->email ?? '',
                                            'notes' => $supplier->notes ?? '',
                                        ]) }})"
                                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                        Editar
                                    </button>
                                    <form method="POST" action="{{ route('suppliers.toggle-active', $supplier) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                            {{ $supplier->active ? 'Desactivar' : 'Activar' }}
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

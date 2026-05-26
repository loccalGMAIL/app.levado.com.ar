<x-app-layout>
    <x-slot name="title">Envases</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'brand', 'supplier_id', 'cost_per_unit']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'brand', 'supplier_id', 'cost_per_unit']) && old('_form') === 'edit';
        $supplierErrorsInCreate = $errors->hasAny(['name', 'phone', 'email', 'notes']) && old('_form') === 'supplier-quick-create';
        $editingDefault = ['id' => null, 'name' => '', 'brand' => '', 'supplier_id' => '', 'cost_per_unit' => ''];
        $editingOnError = $errorsInEdit ? [
            'id'            => old('packaging_id'),
            'name'          => old('name'),
            'brand'         => old('brand'),
            'supplier_id'   => old('supplier_id'),
            'cost_per_unit' => old('cost_per_unit'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'packaging-edit');
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

            @if($packagings->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    Todavía no hay envases. Agregá el primero.
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nombre</th>
                                <th class="px-4 py-3 font-medium">Marca</th>
                                <th class="px-4 py-3 font-medium">Proveedor</th>
                                <th class="px-4 py-3 font-medium text-right">Costo / unidad</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                @can('manage-costs')
                                    <th class="px-4 py-3"></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($packagings as $packaging)
                                <tr class="{{ $packaging->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 font-medium text-corteza">{{ $packaging->name }}</td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">{{ $packaging->brand ?? '—' }}</td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">{{ $packaging->supplier?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-corteza font-mono">
                                        {{ number_format($packaging->cost_per_unit, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($packaging->active)
                                            <span class="text-xs text-green-600 font-medium">activo</span>
                                        @else
                                            <span class="text-xs text-gray-400">inactivo</span>
                                        @endif
                                    </td>
                                    @can('manage-costs')
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-3">
                                                <button type="button"
                                                    @click="openEdit({{ Js::from([
                                                        'id'            => $packaging->id,
                                                        'name'          => $packaging->name,
                                                        'brand'         => $packaging->brand ?? '',
                                                        'supplier_id'   => $packaging->supplier_id ?? '',
                                                        'cost_per_unit' => $packaging->cost_per_unit,
                                                    ]) }})"
                                                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                    Editar
                                                </button>
                                                <form method="POST" action="{{ route('packaging.toggle-active', $packaging) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                        {{ $packaging->active ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-masa-madre">{{ $packagings->count() }} envase(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('packaging.modals.create')
            @include('packaging.modals.edit')
            @include('suppliers.modals.quick-create')
        @endcan

    </div>
</x-app-layout>

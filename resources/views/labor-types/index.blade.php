<x-app-layout>
    <x-slot name="title">Mano de Obra</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'hourly_rate']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'hourly_rate']) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'hourly_rate' => ''];
        $editingOnError = $errorsInEdit ? [
            'id'          => old('labor_type_id'),
            'name'        => old('name'),
            'hourly_rate' => old('hourly_rate'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'labor-type-edit');
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
                    <h2 class="text-base font-semibold text-corteza">Mano de Obra</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Roles de trabajo con su costo por hora para calcular el costo de producción.</p>
                </div>
                @can('manage-costs')
                    <button type="button" id="btn-nuevo-tipo-labor"
                        @click="$dispatch('open-modal', 'labor-type-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nuevo tipo
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
                    <a href="{{ route('labor-types.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @if($laborTypes->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    @if(request('search') || request('status'))
                        No se encontraron tipos con esos filtros.
                    @else
                        Todavía no hay tipos de mano de obra. Agregá el primero.
                    @endif
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nombre</th>
                                <th class="px-4 py-3 font-medium text-right">Costo / hora</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                @can('manage-costs')
                                    <th class="px-4 py-3"></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($laborTypes as $laborType)
                                <tr class="{{ $laborType->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        @can('manage-costs')
                                            <button type="button"
                                                @click="openEdit({{ Js::from([
                                                    'id'          => $laborType->id,
                                                    'name'        => $laborType->name,
                                                    'hourly_rate' => $laborType->hourly_rate,
                                                ]) }})"
                                                class="hover:underline text-left">
                                                {{ $laborType->name }}
                                            </button>
                                        @else
                                            {{ $laborType->name }}
                                        @endcan
                                    </td>
                                    <td class="px-4 py-3 text-right text-corteza font-mono">
                                        $ {{ number_format($laborType->hourly_rate, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($laborType->active)
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
                                                        'id'          => $laborType->id,
                                                        'name'        => $laborType->name,
                                                        'hourly_rate' => $laborType->hourly_rate,
                                                    ]) }})"
                                                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                    Editar
                                                </button>
                                                <form method="POST" action="{{ route('labor-types.toggle-active', $laborType) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                        {{ $laborType->active ? 'Desactivar' : 'Activar' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($laborTypes->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $laborTypes->links() }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-masa-madre">{{ $laborTypes->total() }} tipo(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('labor-types.modals.create')
            @include('labor-types.modals.edit')
        @endcan

    </div>
</x-app-layout>

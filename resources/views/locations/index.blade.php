<x-app-layout>
    <x-slot name="title">Sucursales</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'address', 'city']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'address', 'city']) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'address' => '', 'city' => '', 'is_default' => false];
        $editingOnError = $errorsInEdit ? [
            'id'         => old('location_id'),
            'name'       => old('name'),
            'address'    => old('address'),
            'city'       => old('city'),
            'is_default' => (bool) old('is_default'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'location-edit');
            }
        }">

        <div class="space-y-6">

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Sucursales</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Lugares físicos donde opera el negocio.</p>
                </div>
                <button type="button"
                    @click="$dispatch('open-modal', 'location-create')"
                    class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    + Nueva sucursal
                </button>
            </div>

            @if($locations->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    Todavía no hay sucursales. Creá la primera.
                </div>
            @else
                <div class="space-y-3">
                    @foreach($locations as $location)
                        <div class="bg-white rounded-lg shadow px-5 py-4 flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-corteza text-sm">{{ $location->name }}</span>
                                    @if($location->is_default)
                                        <span class="text-[11px] bg-miga text-masa-madre font-medium px-2 py-0.5 rounded-full">principal</span>
                                    @endif
                                    @if(!$location->active)
                                        <span class="text-[11px] text-gray-400">inactiva</span>
                                    @endif
                                </div>
                                @if($location->address || $location->city)
                                    <p class="text-xs text-masa-madre mt-0.5">
                                        {{ collect([$location->address, $location->city])->filter()->implode(', ') }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <button type="button"
                                    @click="openEdit({{ Js::from([
                                        'id'         => $location->id,
                                        'name'       => $location->name,
                                        'address'    => $location->address ?? '',
                                        'city'       => $location->city ?? '',
                                        'is_default' => $location->is_default,
                                    ]) }})"
                                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                    Editar
                                </button>
                                <form method="POST" action="{{ route('locations.toggle-active', $location) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                        {{ $location->active ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

        @include('locations.modals.create')
        @include('locations.modals.edit')

    </div>
</x-app-layout>

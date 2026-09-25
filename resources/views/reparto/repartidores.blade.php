<x-app-layout>
    <x-slot name="title">Reparto</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'phone', 'notes']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'phone', 'notes']) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'phone' => '', 'notes' => ''];
        $editingOnError = $errorsInEdit ? [
            'id'    => old('delivery_person_id'),
            'name'  => old('name'),
            'phone' => old('phone'),
            'notes' => old('notes'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'delivery-person-edit');
            }
        }">

        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Repartidores</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Quiénes reparten los pedidos de producción.</p>
                </div>
                <button type="button"
                    @click="$dispatch('open-modal', 'delivery-person-create')"
                    class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    + Nuevo repartidor
                </button>
            </div>

            @include('reparto.tabs')

            @if($deliveryPeople->isEmpty())
                <x-empty-state>Todavía no hay repartidores. Creá el primero.</x-empty-state>
            @else
                <div class="space-y-3">
                    @foreach($deliveryPeople as $deliveryPerson)
                        <div class="bg-white rounded-lg shadow px-5 py-4 flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-corteza text-sm">{{ $deliveryPerson->name }}</span>
                                    @if(!$deliveryPerson->active)
                                        <x-status-badge :active="false" label-inactive="inactivo" />
                                    @endif
                                </div>
                                @if($deliveryPerson->phone)
                                    <p class="text-xs text-masa-madre mt-0.5">{{ $deliveryPerson->phone }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button"
                                    @click="openEdit({{ Js::from([
                                        'id'    => $deliveryPerson->id,
                                        'name'  => $deliveryPerson->name,
                                        'phone' => $deliveryPerson->phone ?? '',
                                        'notes' => $deliveryPerson->notes ?? '',
                                    ]) }})"
                                    aria-label="Editar repartidor" title="Editar repartidor"
                                    class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </button>
                                <form method="POST" action="{{ route('reparto.repartidores.toggle-active', $deliveryPerson) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                        aria-label="{{ $deliveryPerson->active ? 'Desactivar' : 'Activar' }}" title="{{ $deliveryPerson->active ? 'Desactivar' : 'Activar' }}"
                                        class="p-1.5 rounded transition-colors {{ $deliveryPerson->active ? 'text-red-500 hover:text-red-700 hover:bg-red-50' : 'text-green-600 hover:text-green-700 hover:bg-green-50' }}">
                                        @if($deliveryPerson->active)
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
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

        @include('reparto.modals.repartidor-create')
        @include('reparto.modals.repartidor-edit')

    </div>
</x-app-layout>

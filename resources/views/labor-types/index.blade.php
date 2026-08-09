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

            <x-list-header title="Mano de Obra"
                subtitle="Roles de trabajo con su costo por hora para calcular el costo de producción.">
                @can('manage-costs')
                    <button type="button" id="btn-nuevo-tipo-labor"
                        @click="$dispatch('open-modal', 'labor-type-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nuevo tipo
                    </button>
                @endcan
            </x-list-header>

            <x-list-filters :reset-route="route('labor-types.index')" />

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
            @endphp

            @if($laborTypes->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('status'))
                        No se encontraron tipos con esos filtros.
                    @else
                        Todavía no hay tipos de mano de obra. Agregá el primero.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$laborTypes" total-label="tipo">
                    <x-slot:head>
                        <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                        <x-sortable-th column="hourly_rate" :sort="$sort" :dir="$dir" align="right">Costo / hora</x-sortable-th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($laborTypes as $laborType)
                        @php
                            $editPayload = [
                                'id'          => $laborType->id,
                                'name'        => $laborType->name,
                                'hourly_rate' => $laborType->hourly_rate,
                            ];
                        @endphp
                        <x-data-table.row :dimmed="! $laborType->active">
                            <x-data-table.cell role="title" class="font-medium text-corteza">
                                @can('manage-costs')
                                    <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                        class="hover:underline text-left">
                                        {{ $laborType->name }}
                                    </button>
                                @else
                                    {{ $laborType->name }}
                                @endcan
                            </x-data-table.cell>

                            <x-data-table.cell role="figure" align="right" class="text-corteza font-mono">
                                $ {{ number_format($laborType->hourly_rate, 2, ',', '.') }}
                                <span class="dt-card-only text-xs text-masa-madre font-sans">/ hora</span>
                            </x-data-table.cell>

                            <x-data-table.cell role="badge">
                                <x-status-badge :active="$laborType->active" />
                            </x-data-table.cell>

                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions">
                                        <button type="button" @click="openEdit({{ Js::from($editPayload) }})"
                                            aria-label="Editar tipo de mano de obra" title="Editar tipo de mano de obra"
                                            class="dt-action">
                                            <x-icon name="pencil" />
                                            <span class="dt-card-only">Editar</span>
                                        </button>
                                        <form method="POST" action="{{ route('labor-types.toggle-active', $laborType) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                aria-label="{{ $laborType->active ? 'Desactivar' : 'Activar' }}"
                                                title="{{ $laborType->active ? 'Desactivar' : 'Activar' }}"
                                                class="dt-action {{ $laborType->active ? 'dt-action--danger' : 'dt-action--success' }}">
                                                <x-icon :name="$laborType->active ? 'eye-off' : 'eye'" />
                                                <span class="dt-card-only">{{ $laborType->active ? 'Desactivar' : 'Activar' }}</span>
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
            @include('labor-types.modals.create')
            @include('labor-types.modals.edit')
        @endcan

    </div>
</x-app-layout>

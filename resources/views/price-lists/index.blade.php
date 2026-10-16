<x-app-layout>
    <x-slot name="title">Listas de Precios</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'adjustment_pct']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'adjustment_pct']) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'adjustment_pct' => '', 'is_default' => false];
        $editPayload = fn ($priceList) => [
            'id'             => $priceList->id,
            'name'           => $priceList->name,
            'adjustment_pct' => $priceList->adjustment_pct,
            'is_default'     => $priceList->is_default,
        ];
        $editingOnError = $errorsInEdit ? [
            'id'             => old('price_list_id'),
            'name'           => old('name'),
            'adjustment_pct' => old('adjustment_pct'),
            'is_default'     => (bool) old('is_default_flag'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'price-list-edit');
            }
        }">

        <div class="space-y-6">

            @if($errors->has('toggle'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ $errors->first('toggle') }}
                </div>
            @endif

            <x-list-header title="Listas de precios" subtitle="Definí distintas listas (mostrador, mayorista, etc.). Los precios de cada lista se cargan en la matriz de Artículos.">
                <a href="{{ route('products.matrix') }}"
                    class="px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                    Matriz de precios →
                </a>
                @can('manage-costs')
                    <button type="button" id="btn-nueva-lista"
                        @click="$dispatch('open-modal', 'price-list-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nueva lista
                    </button>
                @endcan
            </x-list-header>

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
                    <option value="">Todas</option>
                    <option value="active"   @selected(request('status') === 'active')>Activas</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivas</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('price-lists.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
            @endphp

            @if($priceLists->isEmpty())
                <x-empty-state>No se encontraron listas con esos filtros.</x-empty-state>
            @else
                <x-data-table :paginator="$priceLists" total-label="lista">
                    <x-slot:head>
                        <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                        <x-sortable-th column="adjustment_pct" :sort="$sort" :dir="$dir" align="right">% ajuste</x-sortable-th>
                        <th class="px-4 py-3 font-medium text-right">Precios cargados</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($priceLists as $priceList)
                        <x-data-table.row :dimmed="! $priceList->active">
                            <x-data-table.cell role="title">
                                <div class="flex items-center gap-2 font-medium text-corteza">
                                    @can('manage-costs')
                                        <button type="button" @click="openEdit({{ Js::from($editPayload($priceList)) }})"
                                            class="hover:underline text-left">
                                            {{ $priceList->name }}
                                        </button>
                                    @else
                                        {{ $priceList->name }}
                                    @endcan
                                    @if($priceList->is_default)
                                        <span class="text-[10px] uppercase tracking-wide bg-miga text-masa-madre px-1.5 py-0.5 rounded">Base</span>
                                    @endif
                                </div>
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle" align="right" class="text-corteza font-mono">
                                @if($priceList->adjustment_pct !== null)
                                    {{ number_format($priceList->adjustment_pct, 2, ',', '.') }} %<span class="dt-card-only font-sans"> ajuste</span>
                                @else
                                    <span class="text-gray-400 max-md:hidden">—</span><span class="dt-card-only font-sans">Sin ajuste</span>
                                @endif
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle" align="right" class="text-corteza">
                                {{ $priceList->prices_count }}<span class="dt-card-only"> precios</span>
                            </x-data-table.cell>
                            <x-data-table.cell role="badge">
                                <x-status-badge :active="$priceList->active" label-active="activa" label-inactive="inactiva" />
                            </x-data-table.cell>
                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions dt-actions--grid">
                                        @if(! $priceList->is_default && $priceList->adjustment_pct !== null && $priceList->active)
                                            <form method="POST" action="{{ route('price-lists.apply-suggestions', $priceList) }}"
                                                onsubmit="return confirm('¿Aplicar sugerencias a todas las recetas sin precio en esta lista?')">
                                                @csrf
                                                <button type="submit"
                                                    aria-label="Aplicar sugerencias de precio" title="Aplicar sugerencias"
                                                    class="dt-action hover:!text-horno hover:!bg-amber-50">
                                                    <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-bolt"></use></svg>
                                                    <span class="dt-card-only">Aplicar sugerencias</span>
                                                </button>
                                            </form>
                                        @endif
                                        <button type="button" @click="openEdit({{ Js::from($editPayload($priceList)) }})"
                                            aria-label="Editar lista de precios" title="Editar lista de precios"
                                            class="dt-action">
                                            <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-pencil"></use></svg>
                                            <span class="dt-card-only">Editar</span>
                                        </button>
                                        @unless($priceList->is_default)
                                            <form method="POST" action="{{ route('price-lists.toggle-active', $priceList) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    aria-label="{{ $priceList->active ? 'Desactivar' : 'Activar' }}" title="{{ $priceList->active ? 'Desactivar' : 'Activar' }}"
                                                    class="dt-action {{ $priceList->active ? 'dt-action--danger' : 'dt-action--success' }}">
                                                    <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-{{ $priceList->active ? 'eye-off' : 'eye' }}"></use></svg>
                                                    <span class="dt-card-only">{{ $priceList->active ? 'Desactivar' : 'Activar' }}</span>
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </x-data-table.cell>
                            @endcan
                        </x-data-table.row>
                    @endforeach
                </x-data-table>
            @endif

        </div>

        @can('manage-costs')
            @include('price-lists.modals.create')
            @include('price-lists.modals.edit')
        @endcan

    </div>
</x-app-layout>

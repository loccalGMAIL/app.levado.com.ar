<x-app-layout>
    <x-slot name="title">Listas de Precios</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'adjustment_pct']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'adjustment_pct']) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'adjustment_pct' => '', 'is_default' => false];
        $editingOnError = $errorsInEdit ? [
            'id'             => old('price_list_id'),
            'name'           => old('name'),
            'adjustment_pct' => old('adjustment_pct'),
            'is_default'     => (bool) old('is_default_flag'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            mobileExpanded: false,
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

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Listas de Precios</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Definí distintas listas (mostrador, mayorista, etc.) con un precio por receta en cada una.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('price-lists.matrix') }}"
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
                </div>
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
                $sortUrl = fn (string $col): string => request()->url() . '?' . http_build_query(
                    array_merge(request()->except(['sort', 'dir', 'page']), [
                        'sort' => $col,
                        'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
                    ])
                );
                $sortIcon = fn (string $col): string => $sort === $col ? ($dir === 'asc' ? '↑' : '↓') : '';
            @endphp

            @if($priceLists->isEmpty())
                <x-empty-state>No se encontraron listas con esos filtros.</x-empty-state>
            @else
                {{-- Cards (mobile) --}}
                <div :class="mobileExpanded ? 'hidden' : 'md:hidden'" class="space-y-3">
                    @foreach($priceLists as $priceList)
                        <div class="bg-white border border-miga rounded-lg p-4 shadow-sm {{ $priceList->active ? '' : 'opacity-50' }}">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-corteza">{{ $priceList->name }}</span>
                                        @if($priceList->is_default)
                                            <span class="text-[10px] uppercase tracking-wide bg-miga text-masa-madre px-1.5 py-0.5 rounded">Base</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-masa-madre mt-1">
                                        @if($priceList->adjustment_pct !== null)
                                            {{ number_format($priceList->adjustment_pct, 2, ',', '.') }}% ajuste
                                        @else
                                            Sin ajuste
                                        @endif
                                        · {{ $priceList->prices_count }} precios
                                    </div>
                                </div>
                                <x-status-badge :active="$priceList->active" label-active="activa" label-inactive="inactiva" />
                            </div>
                            @can('manage-costs')
                                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-miga flex-wrap">
                                    @if(! $priceList->is_default && $priceList->adjustment_pct !== null && $priceList->active)
                                        <form method="POST" action="{{ route('price-lists.apply-suggestions', $priceList) }}"
                                            onsubmit="return confirm('¿Aplicar sugerencias a todas las recetas sin precio en esta lista?')"
                                            class="flex-1">
                                            @csrf
                                            <button type="submit"
                                                class="w-full py-1.5 px-3 text-sm border border-amber-300 text-horno hover:bg-amber-50 rounded transition-colors">
                                                Aplicar sugerencias
                                            </button>
                                        </form>
                                    @endif
                                    <button type="button"
                                        @click="openEdit({{ Js::from([
                                            'id'             => $priceList->id,
                                            'name'           => $priceList->name,
                                            'adjustment_pct' => $priceList->adjustment_pct,
                                            'is_default'     => $priceList->is_default,
                                        ]) }})"
                                        class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                        Editar
                                    </button>
                                    @unless($priceList->is_default)
                                        <form method="POST" action="{{ route('price-lists.toggle-active', $priceList) }}" class="flex-1">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                class="w-full py-1.5 px-3 text-sm rounded transition-colors {{ $priceList->active ? 'border border-red-300 text-red-600 hover:bg-red-50' : 'border border-green-300 text-green-600 hover:bg-green-50' }}">
                                                {{ $priceList->active ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    @endunless
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
                                <th class="px-4 py-3 font-medium text-right">
                                    <a href="{{ $sortUrl('adjustment_pct') }}" class="hover:text-corteza inline-flex items-center justify-end gap-1">
                                        % ajuste <span class="text-xs">{{ $sortIcon('adjustment_pct') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-medium text-right">Precios cargados</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                @can('manage-costs')
                                    <th class="px-4 py-3"></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($priceLists as $priceList)
                                <tr class="{{ $priceList->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        <div class="flex items-center gap-2">
                                            @can('manage-costs')
                                                <button type="button"
                                                    @click="openEdit({{ Js::from([
                                                        'id'             => $priceList->id,
                                                        'name'           => $priceList->name,
                                                        'adjustment_pct' => $priceList->adjustment_pct,
                                                        'is_default'     => $priceList->is_default,
                                                    ]) }})"
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
                                    </td>
                                    <td class="px-4 py-3 text-right text-corteza font-mono">
                                        @if($priceList->adjustment_pct !== null)
                                            {{ number_format($priceList->adjustment_pct, 2, ',', '.') }} %
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-corteza">
                                        {{ $priceList->prices_count }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-status-badge :active="$priceList->active" label-active="activa" label-inactive="inactiva" />
                                    </td>
                                    @can('manage-costs')
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                @if(! $priceList->is_default && $priceList->adjustment_pct !== null && $priceList->active)
                                                    <form method="POST" action="{{ route('price-lists.apply-suggestions', $priceList) }}"
                                                        onsubmit="return confirm('¿Aplicar sugerencias a todas las recetas sin precio en esta lista?')">
                                                        @csrf
                                                        <button type="submit"
                                                            aria-label="Aplicar sugerencias de precio"
                                                            title="Aplicar sugerencias"
                                                            class="p-1.5 rounded text-masa-madre hover:text-horno hover:bg-amber-50 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                                <button type="button"
                                                    @click="openEdit({{ Js::from([
                                                        'id'             => $priceList->id,
                                                        'name'           => $priceList->name,
                                                        'adjustment_pct' => $priceList->adjustment_pct,
                                                        'is_default'     => $priceList->is_default,
                                                    ]) }})"
                                                    aria-label="Editar lista de precios" title="Editar lista de precios"
                                                    class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                    </svg>
                                                </button>
                                                @unless($priceList->is_default)
                                                    <form method="POST" action="{{ route('price-lists.toggle-active', $priceList) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit"
                                                            aria-label="{{ $priceList->active ? 'Desactivar' : 'Activar' }}" title="{{ $priceList->active ? 'Desactivar' : 'Activar' }}"
                                                            class="p-1.5 rounded transition-colors {{ $priceList->active ? 'text-red-500 hover:text-red-700 hover:bg-red-50' : 'text-green-600 hover:text-green-700 hover:bg-green-50' }}">
                                                            @if($priceList->active)
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
                                                @endunless
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($priceLists->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $priceLists->links() }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-masa-madre">{{ $priceLists->total() }} lista(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('price-lists.modals.create')
            @include('price-lists.modals.edit')
        @endcan

    </div>
</x-app-layout>

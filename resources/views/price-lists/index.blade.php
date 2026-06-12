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
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'price-list-edit');
            }
        }">

        <div class="space-y-6">

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif

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
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    No se encontraron listas con esos filtros.
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
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
                                        @if($priceList->active)
                                            <span class="text-xs text-green-600 font-medium">activa</span>
                                        @else
                                            <span class="text-xs text-gray-400">inactiva</span>
                                        @endif
                                    </td>
                                    @can('manage-costs')
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-3">
                                                <button type="button"
                                                    @click="openEdit({{ Js::from([
                                                        'id'             => $priceList->id,
                                                        'name'           => $priceList->name,
                                                        'adjustment_pct' => $priceList->adjustment_pct,
                                                        'is_default'     => $priceList->is_default,
                                                    ]) }})"
                                                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                    Editar
                                                </button>
                                                @unless($priceList->is_default)
                                                    <form method="POST" action="{{ route('price-lists.toggle-active', $priceList) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit"
                                                            class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                            {{ $priceList->active ? 'Desactivar' : 'Activar' }}
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

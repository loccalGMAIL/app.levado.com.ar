<x-app-layout>
    <x-slot name="title">Gastos Fijos</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'fixed_cost_category_id', 'monthly_amount', 'period']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'fixed_cost_category_id', 'monthly_amount', 'period']) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'fixed_cost_category_id' => '', 'monthly_amount' => '', 'period' => now()->format('Y-m')];
        $editingOnError = $errorsInEdit ? [
            'id'                     => old('fixed_cost_id'),
            'name'                   => old('name'),
            'fixed_cost_category_id' => old('fixed_cost_category_id'),
            'monthly_amount'         => old('monthly_amount'),
            'period'                 => old('period'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            mobileExpanded: false,
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'fixed-cost-edit');
            }
        }">

        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Gastos</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Costos operativos mensuales del negocio: alquiler, servicios, personal y otros.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('fixed-costs.history') }}"
                        class="px-4 py-2 bg-white border border-gray-300 text-corteza text-sm rounded-md hover:bg-harina transition-colors">
                        Historial
                    </a>
                    @can('manage-costs')
                        <button type="button"
                            @click="$dispatch('open-modal', 'fixed-cost-categories')"
                            class="px-4 py-2 bg-white border border-gray-300 text-corteza text-sm rounded-md hover:bg-harina transition-colors">
                            Categorías
                        </button>
                        <button type="button" id="btn-nuevo-gasto"
                            @click="$dispatch('open-modal', 'fixed-cost-create')"
                            class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                            + Nuevo gasto
                        </button>
                    @endcan
                </div>
            </div>

            <x-expense-tabs />

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
                    <option value="">Todos</option>
                    <option value="active"   @selected(request('status') === 'active')>Activos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('status'))
                    <a href="{{ route('fixed-costs.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
            @endphp

            @if($fixedCosts->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('status'))
                        No se encontraron gastos con esos filtros.
                    @else
                        Todavía no hay gastos fijos. Agregá el primero.
                    @endif
                </x-empty-state>
            @else
                                <x-responsive-table>
                    <x-slot:cards>
                    @foreach($fixedCosts as $fixedCost)
                        <div class="bg-white border border-miga rounded-lg p-4 shadow-sm {{ $fixedCost->active ? '' : 'opacity-50' }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-medium text-corteza">{{ $fixedCost->name }}</div>
                                    @if($fixedCost->category)
                                        <div class="text-xs text-masa-madre mt-0.5">{{ $fixedCost->category->name }}</div>
                                    @endif
                                    <div class="text-sm font-mono text-corteza mt-1 [overflow-wrap:anywhere] flex items-center gap-1.5">
                                        $ {{ number_format($fixedCost->monthly_amount, 2, ',', '.') }} / mes
                                        <a href="{{ route('fixed-costs.show-history', $fixedCost) }}"
                                            class="text-masa-madre hover:text-corteza"
                                            aria-label="Ver historial de montos" title="Ver historial de montos">
                                            <x-icon name="clock" class="w-3.5 h-3.5" />
                                        </a>
                                    </div>
                                </div>
                                <x-status-badge :active="$fixedCost->active" />
                            </div>
                            @can('manage-costs')
                                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-miga">
                                    <button type="button"
                                        @click="openEdit({{ Js::from([
                                            'id'                     => $fixedCost->id,
                                            'name'                   => $fixedCost->name,
                                            'fixed_cost_category_id' => $fixedCost->fixed_cost_category_id ?? '',
                                            'monthly_amount'         => $fixedCost->monthly_amount,
                                            'period'                 => now()->format('Y-m'),
                                        ]) }})"
                                        class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                        Editar
                                    </button>
                                    <form method="POST" action="{{ route('fixed-costs.toggle-active', $fixedCost) }}" class="flex-1">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                            class="w-full py-1.5 px-3 text-sm rounded transition-colors {{ $fixedCost->active ? 'border border-red-300 text-red-600 hover:bg-red-50' : 'border border-green-300 text-green-600 hover:bg-green-50' }}">
                                            {{ $fixedCost->active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('fixed-costs.destroy', $fixedCost) }}" class="mt-2"
                                    onsubmit="return confirm('¿Eliminar «{{ addslashes($fixedCost->name) }}»? Deja de contarse en los costos y no se va a listar más. Su historial se conserva.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="w-full py-1.5 px-3 text-xs text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition-colors">
                                        Eliminar
                                    </button>
                                </form>
                            @endcan
                        </div>
                    @endforeach
                    <div class="bg-miga/50 rounded-lg px-4 py-3 text-right text-sm font-semibold text-corteza">
                        Total mensual activo: <span class="font-mono ml-1">$ {{ number_format($totalActive, 2, ',', '.') }}</span>
                    </div>
                    </x-slot:cards>

                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                                <th class="px-4 py-3 font-medium">Categoría</th>
                                <x-sortable-th column="monthly_amount" :sort="$sort" :dir="$dir" align="right">Monto mensual</x-sortable-th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                                @can('manage-costs')
                                    <th class="px-4 py-3"></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($fixedCosts as $fixedCost)
                                <tr class="{{ $fixedCost->active ? '' : 'opacity-50' }}">
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        @can('manage-costs')
                                            <button type="button"
                                                @click="openEdit({{ Js::from([
                                                    'id'                     => $fixedCost->id,
                                                    'name'                   => $fixedCost->name,
                                                    'fixed_cost_category_id' => $fixedCost->fixed_cost_category_id ?? '',
                                                    'monthly_amount'         => $fixedCost->monthly_amount,
                                                    'period'                 => now()->format('Y-m'),
                                                ]) }})"
                                                class="hover:underline text-left">
                                                {{ $fixedCost->name }}
                                            </button>
                                        @else
                                            {{ $fixedCost->name }}
                                        @endcan
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">{{ $fixedCost->category?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-corteza font-mono">
                                        $ {{ number_format($fixedCost->monthly_amount, 2, ',', '.') }}
                                        <a href="{{ route('fixed-costs.show-history', $fixedCost) }}"
                                            class="ml-2 inline-flex items-center text-masa-madre hover:text-corteza"
                                            aria-label="Ver historial de montos" title="Ver historial de montos">
                                            <x-icon name="clock" class="w-3.5 h-3.5" />
                                        </a>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-status-badge :active="$fixedCost->active" />
                                    </td>
                                    @can('manage-costs')
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button"
                                                    @click="openEdit({{ Js::from([
                                                        'id'                     => $fixedCost->id,
                                                        'name'                   => $fixedCost->name,
                                                        'fixed_cost_category_id' => $fixedCost->fixed_cost_category_id ?? '',
                                                        'monthly_amount'         => $fixedCost->monthly_amount,
                                                        'period'                 => now()->format('Y-m'),
                                                    ]) }})"
                                                    aria-label="Editar gasto" title="Editar gasto"
                                                    class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                    </svg>
                                                </button>
                                                <form method="POST" action="{{ route('fixed-costs.toggle-active', $fixedCost) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        aria-label="{{ $fixedCost->active ? 'Desactivar' : 'Activar' }}" title="{{ $fixedCost->active ? 'Desactivar' : 'Activar' }}"
                                                        class="p-1.5 rounded transition-colors {{ $fixedCost->active ? 'text-red-500 hover:text-red-700 hover:bg-red-50' : 'text-green-600 hover:text-green-700 hover:bg-green-50' }}">
                                                        @if($fixedCost->active)
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
                                                <form method="POST" action="{{ route('fixed-costs.destroy', $fixedCost) }}"
                                                    onsubmit="return confirm('¿Eliminar «{{ addslashes($fixedCost->name) }}»? Deja de contarse en los costos y no se va a listar más. Su historial se conserva.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        aria-label="Eliminar gasto" title="Eliminar gasto"
                                                        class="p-1.5 rounded text-masa-madre hover:text-red-600 hover:bg-red-50 transition-colors">
                                                        <x-icon name="trash" class="w-4 h-4" />
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-miga bg-miga/50">
                            <tr>
                                <td colspan="{{ auth()->user()->can('manage-costs') ? 5 : 4 }}"
                                    class="px-4 py-3 text-right text-sm font-semibold text-corteza">
                                    Total mensual activo:
                                    <span class="font-mono ml-2">$ {{ number_format($totalActive, 2, ',', '.') }}</span>
                                </td>
                            </tr>
                        </tfoot>

                    <x-slot:footer>
                        @if($fixedCosts->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $fixedCosts->links() }}
                        </div>
                    @endif
                    </x-slot:footer>
                </x-responsive-table>

                <p class="text-xs text-masa-madre">{{ $fixedCosts->total() }} gasto(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('fixed-costs.modals.create')
            @include('fixed-costs.modals.edit')
            <x-expense-categories-modal
                name="fixed-cost-categories"
                title="Categorías de gastos fijos"
                :categories="$categories"
                :show="$showCategories"
                store-route="fixed-cost-categories.store"
                update-route="fixed-cost-categories.update"
                destroy-route="fixed-cost-categories.destroy" />
        @endcan

    </div>
</x-app-layout>

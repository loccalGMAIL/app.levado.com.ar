<x-app-layout>
    <x-slot name="title">Gastos Fijos</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'fixed_cost_category_id', 'monthly_amount', 'valid_from']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'fixed_cost_category_id', 'monthly_amount', 'valid_from']) && old('_form') === 'edit';
        $editingDefault = ['id' => null, 'name' => '', 'fixed_cost_category_id' => '', 'monthly_amount' => '', 'valid_from' => date('Y-m-d')];
        $editingOnError = $errorsInEdit ? [
            'id'                     => old('fixed_cost_id'),
            'name'                   => old('name'),
            'fixed_cost_category_id' => old('fixed_cost_category_id'),
            'monthly_amount'         => old('monthly_amount'),
            'valid_from'             => old('valid_from'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'fixed-cost-edit');
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
                    <h2 class="text-base font-semibold text-corteza">Gastos Fijos</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Costos operativos mensuales del negocio: alquiler, servicios, personal y otros.</p>
                </div>
                @can('manage-costs')
                    <div class="flex items-center gap-2">
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
                    </div>
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
                    <a href="{{ route('fixed-costs.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @if($fixedCosts->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    @if(request('search') || request('status'))
                        No se encontraron gastos con esos filtros.
                    @else
                        Todavía no hay gastos fijos. Agregá el primero.
                    @endif
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">Nombre</th>
                                <th class="px-4 py-3 font-medium">Categoría</th>
                                <th class="px-4 py-3 font-medium text-right">Monto mensual</th>
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
                                                    'valid_from'             => date('Y-m-d'),
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
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($fixedCost->active)
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
                                                        'id'                     => $fixedCost->id,
                                                        'name'                   => $fixedCost->name,
                                                        'fixed_cost_category_id' => $fixedCost->fixed_cost_category_id ?? '',
                                                        'monthly_amount'         => $fixedCost->monthly_amount,
                                                        'valid_from'             => date('Y-m-d'),
                                                    ]) }})"
                                                    class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                    Editar
                                                </button>
                                                <form method="POST" action="{{ route('fixed-costs.toggle-active', $fixedCost) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                                        {{ $fixedCost->active ? 'Desactivar' : 'Activar' }}
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
                    </table>

                    @if($fixedCosts->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $fixedCosts->links() }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-masa-madre">{{ $fixedCosts->total() }} gasto(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('fixed-costs.modals.create')
            @include('fixed-costs.modals.edit')
            @include('fixed-costs.modals.categories')
        @endcan

    </div>
</x-app-layout>

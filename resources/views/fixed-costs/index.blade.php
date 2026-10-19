<x-app-layout>
    <x-slot name="title">Gastos Fijos</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['name', 'fixed_cost_category_id', 'monthly_amount', 'period']) && old('_form') === 'create';
        $errorsInEdit   = $errors->hasAny(['name', 'fixed_cost_category_id', 'monthly_amount', 'period']) && old('_form') === 'edit';
        $editPayload = fn ($fixedCost) => [
            'id'                     => $fixedCost->id,
            'name'                   => $fixedCost->name,
            'fixed_cost_category_id' => $fixedCost->fixed_cost_category_id ?? '',
            'monthly_amount'         => $fixedCost->monthly_amount,
            'period'                 => now()->format('Y-m'),
        ];
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
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'fixed-cost-edit');
            }
        }">

        <div class="space-y-6">

            <x-list-header title="Gastos" subtitle="Costos operativos mensuales del negocio: alquiler, servicios, personal y otros.">
                <a href="{{ route('fixed-costs.history') }}"
                    class="px-4 py-2 bg-white border border-gray-300 text-corteza text-sm rounded-md hover:bg-harina transition-colors">
                    Historial
                </a>
                <button type="button"
                    @click="$dispatch('open-modal', 'fixed-cost-report')"
                    class="px-4 py-2 bg-white border border-gray-300 text-corteza text-sm rounded-md hover:bg-harina transition-colors">
                    Imprimir
                </button>
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
            </x-list-header>

            <x-expense-tabs />

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <input type="hidden" name="sort" value="{{ request('sort') }}">
                <input type="hidden" name="dir" value="{{ request('dir') }}">
                <div class="flex-1 min-w-48">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Buscar por nombre..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <select name="category"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todas las categorías</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                <select name="status"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todos</option>
                    <option value="active"   @selected(request('status') === 'active')>Activos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('status') || request('category'))
                    <a href="{{ route('fixed-costs.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @php
                $sort = request('sort', 'name');
                $dir  = request('dir', 'asc');
            @endphp

            @if($fixedCosts->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('status') || request('category'))
                        No se encontraron gastos con esos filtros.
                    @else
                        Todavía no hay gastos fijos. Agregá el primero.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$fixedCosts" total-label="gasto">
                    <x-slot:head>
                        <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                        <x-sortable-th column="category" :sort="$sort" :dir="$dir">Categoría</x-sortable-th>
                        <x-sortable-th column="monthly_amount" :sort="$sort" :dir="$dir" align="right">Monto mensual</x-sortable-th>
                        <x-sortable-th column="active" :sort="$sort" :dir="$dir">Estado</x-sortable-th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($fixedCosts as $fixedCost)
                        <x-data-table.row :dimmed="! $fixedCost->active">
                            <x-data-table.cell role="title">
                                @can('manage-costs')
                                    <button type="button" @click="openEdit({{ Js::from($editPayload($fixedCost)) }})"
                                        class="font-medium text-corteza hover:underline text-left">
                                        {{ $fixedCost->name }}
                                    </button>
                                @else
                                    <span class="font-medium text-corteza">{{ $fixedCost->name }}</span>
                                @endcan
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre text-xs">{{ $fixedCost->category?->name }}</x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" class="text-corteza font-mono [overflow-wrap:anywhere]">
                                $ {{ number_format($fixedCost->monthly_amount, 2, ',', '.') }}<span class="dt-card-only font-sans"> / mes</span>
                                <a href="{{ route('fixed-costs.show-history', $fixedCost) }}"
                                    class="ml-1.5 inline-flex items-center text-masa-madre hover:text-corteza align-middle"
                                    aria-label="Ver historial de montos" title="Ver historial de montos">
                                    <svg class="ico w-3.5 h-3.5" aria-hidden="true"><use href="{{ $iconSprite }}#i-clock"></use></svg>
                                </a>
                            </x-data-table.cell>
                            <x-data-table.cell role="badge">
                                <x-status-badge :active="$fixedCost->active" />
                            </x-data-table.cell>
                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions dt-actions--grid">
                                        <button type="button" @click="openEdit({{ Js::from($editPayload($fixedCost)) }})"
                                            aria-label="Editar gasto" title="Editar gasto"
                                            class="dt-action">
                                            <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-pencil"></use></svg>
                                            <span class="dt-card-only">Editar</span>
                                        </button>
                                        <form method="POST" action="{{ route('fixed-costs.toggle-active', $fixedCost) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                aria-label="{{ $fixedCost->active ? 'Desactivar' : 'Activar' }}" title="{{ $fixedCost->active ? 'Desactivar' : 'Activar' }}"
                                                class="dt-action {{ $fixedCost->active ? 'dt-action--danger' : 'dt-action--success' }}">
                                                <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-{{ $fixedCost->active ? 'eye-off' : 'eye' }}"></use></svg>
                                                <span class="dt-card-only">{{ $fixedCost->active ? 'Desactivar' : 'Activar' }}</span>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('fixed-costs.destroy', $fixedCost) }}"
                                            onsubmit="return confirm('¿Eliminar «{{ addslashes($fixedCost->name) }}»? Deja de contarse en los costos y no se va a listar más. Su historial se conserva.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                aria-label="Eliminar gasto" title="Eliminar gasto"
                                                class="dt-action hover:!text-red-600 hover:!bg-red-50">
                                                <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-trash"></use></svg>
                                                <span class="dt-card-only">Eliminar</span>
                                            </button>
                                        </form>
                                    </div>
                                </x-data-table.cell>
                            @endcan
                        </x-data-table.row>
                    @endforeach

                    <x-slot:summary>
                        Total mensual activo: <span class="font-mono ml-2">$ {{ number_format($totalActive, 2, ',', '.') }}</span>
                    </x-slot:summary>
                </x-data-table>
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

        @include('fixed-costs.modals.report')

    </div>
</x-app-layout>

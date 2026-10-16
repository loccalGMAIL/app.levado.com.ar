<x-app-layout>
    <x-slot name="title">Gastos Variables</x-slot>

    @php
        $expenseFields  = ['name', 'description', 'variable_expense_category_id', 'supplier_id', 'amount', 'expense_date', 'receipt'];
        $errorsInCreate = $errors->hasAny($expenseFields) && old('_form') === 've-create';
        $errorsInEdit   = $errors->hasAny($expenseFields) && old('_form') === 've-edit';
        $editPayload = fn ($expense) => [
            'id'                           => $expense->id,
            'name'                         => $expense->name,
            'description'                  => $expense->description,
            'variable_expense_category_id' => $expense->variable_expense_category_id ?? '',
            'supplier_id'                  => $expense->supplier_id ?? '',
            'amount'                       => $expense->amount,
            'expense_date'                 => $expense->expense_date->format('Y-m-d'),
            'receipt_image_path'           => $expense->receipt_image_path,
        ];
        $editingDefault = ['id' => null, 'name' => '', 'description' => '', 'variable_expense_category_id' => '', 'supplier_id' => '', 'amount' => '', 'expense_date' => date('Y-m-d'), 'receipt_image_path' => null];
        $editingOnError = $errorsInEdit ? [
            'id'                           => old('variable_expense_id'),
            'name'                         => old('name'),
            'description'                  => old('description'),
            'variable_expense_category_id' => old('variable_expense_category_id'),
            'supplier_id'                  => old('supplier_id'),
            'amount'                       => old('amount'),
            'expense_date'                 => old('expense_date'),
            'receipt_image_path'           => old('receipt_image_path'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'variable-expense-edit');
            }
        }">

        <div class="space-y-6">

            <x-list-header title="Gastos" subtitle="Gastos ocasionales o imprevistos. No intervienen en el costo de las recetas.">
                <button type="button"
                    @click="$dispatch('open-modal', 'fixed-cost-report')"
                    class="px-4 py-2 bg-white border border-gray-300 text-corteza text-sm rounded-md hover:bg-harina transition-colors">
                    Imprimir
                </button>
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'variable-expense-categories')"
                        class="px-4 py-2 bg-white border border-gray-300 text-corteza text-sm rounded-md hover:bg-harina transition-colors">
                        Categorías
                    </button>
                    <button type="button" id="btn-nuevo-gasto-variable"
                        @click="$dispatch('open-modal', 'variable-expense-create')"
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
                        placeholder="Buscar por nombre o descripción..."
                        class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                {{-- Dropdown de checkboxes en vez de un <select multiple>: Tom Select
                     mostraba los tags elegidos sin forma obvia de sacarlos (hacía falta
                     seleccionar el tag y apretar Backspace). Tildar/destildar acá es la
                     misma interacción para elegir y para sacar. --}}
                <div class="relative" x-data="{ open: false, selected: {{ Js::from((array) request('category', [])) }} }" @click.outside="open = false">
                    <button type="button" @click="open = !open"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno px-3 py-2 bg-white text-corteza flex items-center gap-2 min-w-[11rem] justify-between">
                        <span x-text="selected.length ? 'Categoría (' + selected.length + ')' : 'Todas las categorías'"></span>
                        <svg class="w-4 h-4 text-masa-madre shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    <div x-show="open" x-transition
                        class="absolute z-20 mt-1 w-56 max-h-64 overflow-y-auto bg-white border border-gray-300 rounded-md shadow-lg p-2 space-y-1"
                        style="display: none;">
                        @foreach($categories as $cat)
                            <label class="flex items-center gap-2 text-sm text-corteza px-1.5 py-1 hover:bg-miga rounded cursor-pointer">
                                <input type="checkbox" name="category[]" value="{{ $cat->id }}" x-model="selected"
                                    class="rounded border-gray-300 text-horno focus:ring-horno">
                                {{ $cat->name }}
                            </label>
                        @endforeach
                        <button type="button" @click="selected = []" x-show="selected.length"
                            class="w-full text-left text-xs text-masa-madre hover:text-corteza hover:underline mt-1 pt-1 border-t border-miga">
                            Limpiar
                        </button>
                    </div>
                </div>
                <select name="supplier"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todos los proveedores</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(request('supplier') == $supplier->id)>
                            {{ $supplier->name }}{{ $supplier->active ? '' : ' (inactivo)' }}
                        </option>
                    @endforeach
                </select>
                <div>
                    <label for="filter_ve_from" class="block text-xs text-masa-madre mb-1">Desde</label>
                    <input type="date" id="filter_ve_from" name="from" value="{{ request('from') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <div>
                    <label for="filter_ve_to" class="block text-xs text-masa-madre mb-1">Hasta</label>
                    <input type="date" id="filter_ve_to" name="to" value="{{ request('to') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('search') || request('category') || request('supplier') || request('from') || request('to'))
                    <a href="{{ route('variable-expenses.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @php
                $sort = request('sort', 'expense_date');
                $dir  = request('dir', 'asc');
            @endphp

            @if($variableExpenses->isEmpty())
                <x-empty-state>
                    @if(request('search') || request('category') || request('supplier') || request('from') || request('to'))
                        No se encontraron gastos con esos filtros.
                    @else
                        Todavía no hay gastos variables. Agregá el primero.
                    @endif
                </x-empty-state>
            @else
                <x-data-table :paginator="$variableExpenses" total-label="gasto">
                    <x-slot:head>
                        <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                        <th class="px-4 py-3 font-medium">Categoría</th>
                        <th class="px-4 py-3 font-medium">Proveedor</th>
                        <x-sortable-th column="expense_date" :sort="$sort" :dir="$dir">Fecha</x-sortable-th>
                        <x-sortable-th column="amount" :sort="$sort" :dir="$dir" align="right">Monto</x-sortable-th>
                        @can('manage-costs')
                            <th class="px-4 py-3"></th>
                        @endcan
                    </x-slot:head>

                    @foreach($variableExpenses as $variableExpense)
                        <x-data-table.row>
                            <x-data-table.cell role="title">
                                <div class="flex items-center gap-1.5 font-medium text-corteza">
                                    @can('manage-costs')
                                        <button type="button" @click="openEdit({{ Js::from($editPayload($variableExpense)) }})"
                                            class="hover:underline text-left">
                                            {{ $variableExpense->name }}
                                        </button>
                                    @else
                                        {{ $variableExpense->name }}
                                    @endcan
                                    @if($variableExpense->receipt_image_path)
                                        <x-receipt-link :href="route('variable-expenses.receipt', $variableExpense)" />
                                    @endif
                                </div>
                                @if($variableExpense->description)
                                    <div class="text-xs font-normal text-masa-madre mt-0.5" title="{{ $variableExpense->description }}">
                                        {{ \Illuminate\Support\Str::limit($variableExpense->description, 60) }}
                                    </div>
                                @endif
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre text-xs">{{ $variableExpense->category?->name }}</x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre text-xs">{{ $variableExpense->supplier?->name }}</x-data-table.cell>
                            <x-data-table.cell role="badge" class="text-masa-madre max-md:text-xs">{{ $variableExpense->expense_date->format('d/m/Y') }}</x-data-table.cell>
                            <x-data-table.cell role="figure" align="right" class="text-corteza font-mono [overflow-wrap:anywhere]">
                                $ {{ number_format($variableExpense->amount, 2, ',', '.') }}
                            </x-data-table.cell>
                            @can('manage-costs')
                                <x-data-table.cell role="actions">
                                    <div class="dt-actions">
                                        <button type="button" @click="openEdit({{ Js::from($editPayload($variableExpense)) }})"
                                            aria-label="Editar gasto" title="Editar gasto"
                                            class="dt-action">
                                            <svg class="ico" aria-hidden="true"><use href="{{ $iconSprite }}#i-pencil"></use></svg>
                                            <span class="dt-card-only">Editar</span>
                                        </button>
                                        <form method="POST" action="{{ route('variable-expenses.destroy', $variableExpense) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                onclick="return confirm('¿Eliminar «{{ addslashes($variableExpense->name) }}»?')"
                                                aria-label="Eliminar gasto" title="Eliminar gasto"
                                                class="dt-action dt-action--danger">
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
                        Total del período: <span class="font-mono ml-2">$ {{ number_format($totalPeriod, 2, ',', '.') }}</span>
                    </x-slot:summary>
                </x-data-table>
            @endif

        </div>

        @can('manage-costs')
            @include('variable-expenses.modals.create')
            @include('variable-expenses.modals.edit')
            @include('suppliers.modals.quick-create')
            <x-expense-categories-modal
                name="variable-expense-categories"
                title="Categorías de gastos variables"
                :categories="$categories"
                :show="$showCategories"
                store-route="variable-expense-categories.store"
                update-route="variable-expense-categories.update"
                destroy-route="variable-expense-categories.destroy" />
        @endcan

        @include('fixed-costs.modals.report', [
            'context' => 'variable',
            'from' => request('from'),
            'to' => request('to'),
        ])

    </div>
</x-app-layout>

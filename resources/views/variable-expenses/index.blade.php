<x-app-layout>
    <x-slot name="title">Gastos Variables</x-slot>

    @php
        $expenseFields  = ['name', 'variable_expense_category_id', 'supplier_id', 'amount', 'expense_date', 'receipt'];
        $errorsInCreate = $errors->hasAny($expenseFields) && old('_form') === 've-create';
        $errorsInEdit   = $errors->hasAny($expenseFields) && old('_form') === 've-edit';
        $editingDefault = ['id' => null, 'name' => '', 'variable_expense_category_id' => '', 'supplier_id' => '', 'amount' => '', 'expense_date' => date('Y-m-d'), 'receipt_image_path' => null];
        $editingOnError = $errorsInEdit ? [
            'id'                           => old('variable_expense_id'),
            'name'                         => old('name'),
            'variable_expense_category_id' => old('variable_expense_category_id'),
            'supplier_id'                  => old('supplier_id'),
            'amount'                       => old('amount'),
            'expense_date'                 => old('expense_date'),
            'receipt_image_path'           => old('receipt_image_path'),
        ] : $editingDefault;
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{
            mobileExpanded: false,
            editing: {{ Js::from($editingOnError) }},
            openEdit(record) {
                this.editing = record;
                $dispatch('open-modal', 'variable-expense-edit');
            }
        }">

        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Gastos</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Gastos ocasionales o imprevistos. No intervienen en el costo de las recetas.</p>
                </div>
                @can('manage-costs')
                    <div class="flex items-center gap-2">
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
                    </div>
                @endcan
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
                <select name="category"
                    class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    <option value="">Todas las categorías</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
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
                                <x-responsive-table>
                    <x-slot:cards>
                    @foreach($variableExpenses as $variableExpense)
                        <div class="bg-white border border-miga rounded-lg p-4 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium text-corteza flex items-center gap-1.5">
                                        {{ $variableExpense->name }}
                                        @if($variableExpense->receipt_image_path)
                                            <x-receipt-link :href="route('variable-expenses.receipt', $variableExpense)" />
                                        @endif
                                    </div>
                                    <div class="text-xs text-masa-madre mt-0.5">
                                        {{ implode(' · ', array_filter([$variableExpense->category?->name, $variableExpense->supplier?->name])) ?: '—' }}
                                    </div>
                                    <div class="text-sm font-mono text-corteza mt-1">$ {{ number_format($variableExpense->amount, 2, ',', '.') }}</div>
                                </div>
                                <div class="text-xs text-masa-madre shrink-0">{{ $variableExpense->expense_date->format('d/m/Y') }}</div>
                            </div>
                            @can('manage-costs')
                                <div class="flex items-center gap-2 mt-3 pt-3 border-t border-miga">
                                    <button type="button"
                                        @click="openEdit({{ Js::from([
                                            'id'                           => $variableExpense->id,
                                            'name'                         => $variableExpense->name,
                                            'variable_expense_category_id' => $variableExpense->variable_expense_category_id ?? '',
                                            'supplier_id'                  => $variableExpense->supplier_id ?? '',
                                            'amount'                       => $variableExpense->amount,
                                            'expense_date'                 => $variableExpense->expense_date->format('Y-m-d'),
                                            'receipt_image_path'           => $variableExpense->receipt_image_path,
                                        ]) }})"
                                        class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                        Editar
                                    </button>
                                    <form method="POST" action="{{ route('variable-expenses.destroy', $variableExpense) }}" class="flex-1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            onclick="return confirm('¿Eliminar «{{ addslashes($variableExpense->name) }}»?')"
                                            class="w-full py-1.5 px-3 text-sm rounded transition-colors border border-red-300 text-red-600 hover:bg-red-50">
                                            Eliminar
                                        </button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @endforeach
                    <div class="bg-miga/50 rounded-lg px-4 py-3 text-right text-sm font-semibold text-corteza">
                        Total del período: <span class="font-mono ml-1">$ {{ number_format($totalPeriod, 2, ',', '.') }}</span>
                    </div>
                    </x-slot:cards>

                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <x-sortable-th column="name" :sort="$sort" :dir="$dir">Nombre</x-sortable-th>
                                <th class="px-4 py-3 font-medium">Categoría</th>
                                <th class="px-4 py-3 font-medium">Proveedor</th>
                                <x-sortable-th column="expense_date" :sort="$sort" :dir="$dir">Fecha</x-sortable-th>
                                <x-sortable-th column="amount" :sort="$sort" :dir="$dir" align="right">Monto</x-sortable-th>
                                @can('manage-costs')
                                    <th class="px-4 py-3"></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($variableExpenses as $variableExpense)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        <div class="flex items-center gap-1.5">
                                            @can('manage-costs')
                                                <button type="button"
                                                    @click="openEdit({{ Js::from([
                                                        'id'                           => $variableExpense->id,
                                                        'name'                         => $variableExpense->name,
                                                        'variable_expense_category_id' => $variableExpense->variable_expense_category_id ?? '',
                                                        'supplier_id'                  => $variableExpense->supplier_id ?? '',
                                                        'amount'                       => $variableExpense->amount,
                                                        'expense_date'                 => $variableExpense->expense_date->format('Y-m-d'),
                                                        'receipt_image_path'           => $variableExpense->receipt_image_path,
                                                    ]) }})"
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
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">{{ $variableExpense->category?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-masa-madre text-xs">{{ $variableExpense->supplier?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-masa-madre">{{ $variableExpense->expense_date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-right text-corteza font-mono">
                                        $ {{ number_format($variableExpense->amount, 2, ',', '.') }}
                                    </td>
                                    @can('manage-costs')
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button"
                                                    @click="openEdit({{ Js::from([
                                                        'id'                           => $variableExpense->id,
                                                        'name'                         => $variableExpense->name,
                                                        'variable_expense_category_id' => $variableExpense->variable_expense_category_id ?? '',
                                                        'supplier_id'                  => $variableExpense->supplier_id ?? '',
                                                        'amount'                       => $variableExpense->amount,
                                                        'expense_date'                 => $variableExpense->expense_date->format('Y-m-d'),
                                                        'receipt_image_path'           => $variableExpense->receipt_image_path,
                                                    ]) }})"
                                                    aria-label="Editar gasto" title="Editar gasto"
                                                    class="p-1.5 rounded text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                                    </svg>
                                                </button>
                                                <form method="POST" action="{{ route('variable-expenses.destroy', $variableExpense) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        onclick="return confirm('¿Eliminar «{{ addslashes($variableExpense->name) }}»?')"
                                                        aria-label="Eliminar gasto" title="Eliminar gasto"
                                                        class="p-1.5 rounded text-red-500 hover:text-red-700 hover:bg-red-50 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                                        </svg>
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
                                <td colspan="{{ auth()->user()->can('manage-costs') ? 6 : 5 }}"
                                    class="px-4 py-3 text-right text-sm font-semibold text-corteza">
                                    Total del período:
                                    <span class="font-mono ml-2">$ {{ number_format($totalPeriod, 2, ',', '.') }}</span>
                                </td>
                            </tr>
                        </tfoot>

                    <x-slot:footer>
                        @if($variableExpenses->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $variableExpenses->links() }}
                        </div>
                    @endif
                    </x-slot:footer>
                </x-responsive-table>

                <p class="text-xs text-masa-madre">{{ $variableExpenses->total() }} gasto(s) en total.</p>
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

    </div>
</x-app-layout>

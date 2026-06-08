<x-app-layout>
    <x-slot name="title">Compras</x-slot>

    @php
        $errorsInCreate = $errors->hasAny(['supplier_id', 'invoice_number', 'invoice_date', 'notes']) && old('_form') === 'create';
    @endphp

    <div class="py-8 px-6 lg:px-8"
        x-data="{}">

        <div class="space-y-6">

            @if(session('status'))
                <div class="p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('status') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Compras</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Facturas de compra de insumos y envases.</p>
                </div>
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'purchase-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                        + Nueva compra
                    </button>
                @endcan
            </div>

            <form method="GET" class="flex gap-3 items-end flex-wrap">
                <div>
                    <label class="block text-xs font-medium text-masa-madre mb-1">Proveedor</label>
                    <select name="supplier_id"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todos</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                @selected(request('supplier_id') == $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-masa-madre mb-1">Desde</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <div>
                    <label class="block text-xs font-medium text-masa-madre mb-1">Hasta</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                <button type="submit" class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                    Filtrar
                </button>
                @if(request('supplier_id') || request('from') || request('to'))
                    <a href="{{ route('purchases.index') }}" class="text-sm text-masa-madre hover:underline self-center">Limpiar</a>
                @endif
            </form>

            @if($purchases->isEmpty())
                <div class="bg-white rounded-lg shadow p-8 text-center text-masa-madre text-sm">
                    @if(request('supplier_id') || request('from') || request('to'))
                        No se encontraron compras con esos filtros.
                    @else
                        Todavía no hay compras registradas. Cargá la primera factura.
                    @endif
                </div>
            @else
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-miga text-masa-madre border-b border-miga">
                            <tr>
                                <th class="px-4 py-3 font-medium">Fecha</th>
                                <th class="px-4 py-3 font-medium">Proveedor</th>
                                <th class="px-4 py-3 font-medium">N° Factura</th>
                                <th class="px-4 py-3 font-medium text-center">Ítems</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga">
                            @foreach($purchases as $purchase)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-sm text-corteza">
                                        {{ $purchase->invoice_date->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3 font-medium text-corteza">
                                        {{ $purchase->supplier->name }}
                                    </td>
                                    <td class="px-4 py-3 text-masa-madre font-mono text-xs">
                                        {{ $purchase->invoice_number ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-masa-madre">
                                        {{ $purchase->lines_count }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('purchases.show', $purchase) }}"
                                            class="text-xs text-masa-madre hover:text-corteza hover:underline">
                                            Ver detalle →
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($purchases->hasPages())
                        <div class="px-4 py-3 border-t border-miga">
                            {{ $purchases->links() }}
                        </div>
                    @endif
                </div>

                <p class="text-xs text-masa-madre">{{ $purchases->total() }} compra(s) en total.</p>
            @endif

        </div>

        @can('manage-costs')
            @include('purchases.modals.create')
            @include('suppliers.modals.quick-create')
        @endcan

    </div>
</x-app-layout>

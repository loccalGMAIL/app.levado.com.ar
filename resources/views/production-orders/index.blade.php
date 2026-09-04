<x-app-layout>
    <x-slot name="title">Órdenes de producción</x-slot>

    <div class="py-8 px-6 lg:px-8" x-data="{ mobileExpanded: false }">
        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Órdenes de producción</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Agrupá los pedidos del día (o los espontáneos) y producilos de una.</p>
                    <a href="{{ route('production-order-templates.index') }}" class="text-sm text-horno hover:underline">Plantillas →</a>
                </div>
                @can('manage-costs')
                    <button type="button"
                        @click="$dispatch('open-modal', 'production-order-create')"
                        class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors shrink-0">
                        + Nueva orden
                    </button>
                @endcan
            </div>

            <form method="GET" action="{{ route('production-orders.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Tipo</label>
                    <select name="type" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todas</option>
                        @foreach(\App\Enums\ProductionOrderType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Estado</label>
                    <select name="status" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        <option value="">Todos</option>
                        @foreach(\App\Enums\ProductionOrderStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-masa-madre mb-1">Fecha</label>
                    <input type="date" name="date" value="{{ request('date') }}" onchange="this.form.submit()"
                        class="border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                </div>
                @if(request()->hasAny(['type', 'status', 'date']))
                    <a href="{{ route('production-orders.index') }}" class="text-sm text-masa-madre hover:text-corteza hover:underline pb-2">Limpiar filtros</a>
                @endif
            </form>

            @if($orders->isEmpty())
                <x-empty-state>Todavía no hay órdenes de producción. Creá la primera.</x-empty-state>
            @else
                <x-responsive-table>
                    <x-slot:cards>
                    @foreach($orders as $order)
                        <a href="{{ route('production-orders.show', $order) }}"
                            class="block bg-white border border-miga rounded-lg p-4 shadow-sm {{ $order->isCancelled() ? 'opacity-60' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <x-production-order-type-badge :type="$order->type" />
                                <x-production-order-status-badge :status="$order->status" />
                            </div>
                            <div class="text-xs text-masa-madre mt-2">
                                {{ $order->scheduled_for?->format('d/m/Y') ?? 'Sin fecha' }}
                            </div>
                            <div class="mt-1 text-sm text-corteza">
                                {{ $order->production_order_requests_count }} pedido(s)
                            </div>
                        </a>
                    @endforeach
                    </x-slot:cards>

                    <thead class="bg-miga text-masa-madre border-b border-miga">
                        <tr>
                            <th class="px-4 py-3 font-medium">Fecha</th>
                            <th class="px-4 py-3 font-medium">Tipo</th>
                            <th class="px-4 py-3 font-medium text-right">Pedidos</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($orders as $order)
                            <tr class="{{ $order->isCancelled() ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3 text-masa-madre whitespace-nowrap">{{ $order->scheduled_for?->format('d/m/Y') ?? 'Sin fecha' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('production-orders.show', $order) }}" class="hover:underline">
                                        <x-production-order-type-badge :type="$order->type" />
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-right text-corteza font-mono">{{ $order->production_order_requests_count }}</td>
                                <td class="px-4 py-3">
                                    <x-production-order-status-badge :status="$order->status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('production-orders.show', $order) }}" class="text-sm text-horno hover:underline">Ver</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <x-slot:footer>
                        @if($orders->hasPages())
                            <div class="px-4 py-3 border-t border-miga">
                                {{ $orders->links() }}
                            </div>
                        @endif
                    </x-slot:footer>
                </x-responsive-table>
            @endif

        </div>

        @can('manage-costs')
            @include('production-orders.modals.create')
        @endcan
    </div>
</x-app-layout>

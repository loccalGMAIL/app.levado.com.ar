<x-app-layout>
    <x-slot name="title">Órdenes de producción</x-slot>

    @php
        // Hoy/Mañana: con el horizonte de 7 días del materializador, "hoy"
        // queda enterrado a mitad de un listado ordenado por fecha sin esto.
        $dateLabel = function ($order) {
            if ($order->scheduled_for === null) {
                return 'Sin fecha';
            }
            if ($order->scheduled_for->isToday()) {
                return 'Hoy';
            }
            if ($order->scheduled_for->isTomorrow()) {
                return 'Mañana';
            }

            return $order->scheduled_for->format('d/m/Y');
        };

        // Sin "Orden #": la columna ya dice "Orden", mostrar el prefijo de
        // nuevo sería redundante. Sólo en esta lista — numberLabel() (con
        // "Orden #") se sigue usando en el detalle, la planilla de reparto,
        // breadcrumbs y mensajes de confirmación.
        $orderNumber = fn ($order) => str_pad((string) $order->number, 5, '0', STR_PAD_LEFT);

        // <x-sortable-th> arma su propia URL desde request(); este cálculo
        // sólo es para pintar la flecha activa (mismo patrón que labor-types/
        // stock/ingredients/etc.) — el orden real lo aplica el controller.
        $sort = request('sort', 'number');
        $dir = request('dir', 'desc');
    @endphp

    {{-- products viaja una sola vez acá (root x-data) y el modal "+ Nuevo
         pedido" lo lee por referencia, igual que hace show.blade.php con
         cada grilla de pedido — ver partials/lines-grid.blade.php. --}}
    <div class="py-8 px-6 lg:px-8" x-data="{ mobileExpanded: false, products: @js($products) }">
        <div class="space-y-6">

            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-corteza">Órdenes de producción</h2>
                    <p class="text-sm text-masa-madre mt-0.5">Cargá los pedidos y la orden del día se va armando sola.</p>
                </div>
                @can('manage-costs')
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button"
                            @click="$dispatch('open-modal', 'production-request-create')"
                            class="px-4 py-2 bg-corteza text-white text-sm rounded-md hover:bg-horno transition-colors">
                            + Nuevo pedido
                        </button>
                        <button type="button"
                            @click="$dispatch('open-modal', 'production-instant-create')"
                            class="px-4 py-2 border border-corteza text-corteza text-sm rounded-md hover:bg-miga transition-colors">
                            ⚡ Orden instantánea
                        </button>
                    </div>
                @endcan
            </div>

            @include('production-orders.tabs')

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
                <x-empty-state>Todavía no hay órdenes de producción. Cargá el primer pedido.</x-empty-state>
            @else
                <x-responsive-table>
                    <x-slot:cards>
                    @foreach($orders as $order)
                        <div class="bg-white border border-miga rounded-lg p-4 shadow-sm {{ $order->isCancelled() ? 'opacity-60' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-medium text-corteza">
                                    {{ $orderNumber($order) }}
                                    @if($order->recurring_requests_count > 0)
                                        <span title="Tiene pedidos recurrentes">🔁</span>
                                    @endif
                                </span>
                                <x-production-order-status-badge :status="$order->status" />
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <x-production-order-type-badge :type="$order->type" />
                            </div>
                            <div class="text-xs text-masa-madre mt-2">
                                {{ $dateLabel($order) }}
                            </div>
                            <div class="mt-1 text-sm text-corteza">
                                {{ $order->production_order_requests_count }} pedido(s)
                            </div>

                            <div class="flex items-center gap-2 mt-3 pt-3 border-t border-miga">
                                <a href="{{ route('production-orders.show', $order) }}"
                                    class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                    Ver
                                </a>
                                <a href="{{ route('production-orders.production-sheet', $order) }}" target="_blank"
                                    class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                    Producción
                                </a>
                                <a href="{{ route('production-orders.delivery-sheet', $order) }}" target="_blank"
                                    class="flex-1 py-1.5 px-3 text-sm border border-gray-300 rounded text-corteza hover:bg-miga transition-colors text-center">
                                    Reparto
                                </a>
                                @can('manage-costs')
                                    @if($order->isDraft())
                                        <form method="POST" action="{{ route('production-orders.transition', $order) }}" class="flex-1">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="confirmed">
                                            <button type="submit"
                                                class="w-full py-1.5 px-3 text-sm border border-corteza text-corteza hover:bg-miga rounded transition-colors">
                                                Confirmar
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    @endforeach
                    </x-slot:cards>

                    <thead class="bg-miga text-masa-madre border-b border-miga">
                        <tr>
                            <x-sortable-th column="number" :sort="$sort" :dir="$dir">Orden</x-sortable-th>
                            <x-sortable-th column="scheduled_for" :sort="$sort" :dir="$dir">Fecha</x-sortable-th>
                            <x-sortable-th column="type" :sort="$sort" :dir="$dir">Tipo</x-sortable-th>
                            <x-sortable-th column="production_order_requests_count" :sort="$sort" :dir="$dir" align="right">Pedidos</x-sortable-th>
                            <x-sortable-th column="status" :sort="$sort" :dir="$dir">Estado</x-sortable-th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-miga">
                        @foreach($orders as $order)
                            <tr class="{{ $order->isCancelled() ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3 font-medium text-corteza">
                                    <a href="{{ route('production-orders.show', $order) }}" class="hover:underline">
                                        {{ $orderNumber($order) }}
                                    </a>
                                    @if($order->recurring_requests_count > 0)
                                        <span title="Tiene pedidos recurrentes">🔁</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-masa-madre whitespace-nowrap">{{ $dateLabel($order) }}</td>
                                <td class="px-4 py-3">
                                    <x-production-order-type-badge :type="$order->type" />
                                </td>
                                <td class="px-4 py-3 text-right text-corteza font-mono">{{ $order->production_order_requests_count }}</td>
                                <td class="px-4 py-3">
                                    <x-production-order-status-badge :status="$order->status" />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('production-orders.show', $order) }}" class="text-sm text-horno hover:underline">Ver</a>
                                        <a href="{{ route('production-orders.production-sheet', $order) }}" target="_blank" class="text-sm text-horno hover:underline">Producción</a>
                                        <a href="{{ route('production-orders.delivery-sheet', $order) }}" target="_blank" class="text-sm text-horno hover:underline">Reparto</a>
                                        @can('manage-costs')
                                            @if($order->isDraft())
                                                <form method="POST" action="{{ route('production-orders.transition', $order) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button type="submit" class="text-sm text-horno hover:underline">Confirmar</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
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
            @include('production-orders.modals.request-create')
            @include('production-orders.modals.instant-create')
        @endcan
    </div>
</x-app-layout>

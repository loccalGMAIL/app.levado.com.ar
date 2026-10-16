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
    <div class="py-8 px-6 lg:px-8" x-data="{ products: @js($products) }">
        <div class="space-y-6">

            <x-list-header title="Órdenes de producción" subtitle="Cargá los pedidos y la orden del día se va armando sola.">
                @can('manage-costs')
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
                @endcan
            </x-list-header>

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
                <x-data-table :paginator="$orders" total-label="orden" total-suffix="(es)">
                    <x-slot:head>
                        <x-sortable-th column="number" :sort="$sort" :dir="$dir">Orden</x-sortable-th>
                        <x-sortable-th column="scheduled_for" :sort="$sort" :dir="$dir">Fecha</x-sortable-th>
                        <x-sortable-th column="type" :sort="$sort" :dir="$dir">Tipo</x-sortable-th>
                        <x-sortable-th column="production_order_requests_count" :sort="$sort" :dir="$dir" align="right">Pedidos</x-sortable-th>
                        <x-sortable-th column="status" :sort="$sort" :dir="$dir">Estado</x-sortable-th>
                        <th class="px-4 py-3"></th>
                    </x-slot:head>

                    @foreach($orders as $order)
                        <x-data-table.row :dimmed="$order->isCancelled()">
                            <x-data-table.cell role="title">
                                <a href="{{ route('production-orders.show', $order) }}" class="font-medium text-corteza hover:underline">
                                    {{ $orderNumber($order) }}
                                </a>
                                @if($order->recurring_requests_count > 0)
                                    <span title="Tiene pedidos recurrentes">🔁</span>
                                @endif
                            </x-data-table.cell>
                            <x-data-table.cell role="subtitle" class="text-masa-madre whitespace-nowrap">{{ $dateLabel($order) }}</x-data-table.cell>
                            <x-data-table.cell role="subtitle">
                                <x-production-order-type-badge :type="$order->type" />
                            </x-data-table.cell>
                            <x-data-table.cell role="meta" align="right" class="text-corteza font-mono">{{ $order->production_order_requests_count }}<span class="dt-card-only font-sans"> pedido(s)</span></x-data-table.cell>
                            <x-data-table.cell role="badge">
                                <x-production-order-status-badge :status="$order->status" />
                            </x-data-table.cell>
                            <x-data-table.cell role="actions">
                                <div class="dt-actions dt-actions--grid">
                                    <a href="{{ route('production-orders.show', $order) }}" class="dt-action !text-horno">Ver</a>
                                    <a href="{{ route('production-orders.production-sheet', $order) }}" target="_blank" class="dt-action !text-horno">Producción</a>
                                    <a href="{{ route('production-orders.delivery-sheet', $order) }}" target="_blank" class="dt-action !text-horno">Reparto</a>
                                    @can('manage-costs')
                                        @if($order->isDraft())
                                            <form method="POST" action="{{ route('production-orders.transition', $order) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="status" value="confirmed">
                                                <button type="submit" class="dt-action !text-horno">Confirmar</button>
                                            </form>
                                        @endif
                                    @endcan
                                </div>
                            </x-data-table.cell>
                        </x-data-table.row>
                    @endforeach
                </x-data-table>
            @endif

        </div>

        @can('manage-costs')
            @include('production-orders.modals.request-create')
            @include('production-orders.modals.instant-create')
        @endcan
    </div>
</x-app-layout>

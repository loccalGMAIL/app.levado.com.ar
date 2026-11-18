<x-app-layout>
    <x-slot name="title">Reparto</x-slot>

    @php
        $dayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $weekUrl = fn (\Carbon\CarbonImmutable $monday) => route('reparto.pedidos.index', array_filter([
            'customer' => $customer?->id,
            'start' => $monday->toDateString(),
        ]));
        $isCurrentWeek = $start->isSameDay(now()->startOfWeek(\Carbon\Carbon::MONDAY));
    @endphp

    <div class="py-8 px-6 lg:px-8" x-data>
        <div class="space-y-6">

            <x-list-header title="Pedidos de clientes" subtitle="Lo que pide cada cliente por día. Suma en la planilla de Pedidos de cada artículo y arma la hoja del repartidor." />

            @include('reparto.tabs')

            @if($customers->isEmpty())
                <x-empty-state>Todavía no hay clientes activos. Creá el primero en la pestaña Clientes.</x-empty-state>
            @else
                {{-- Hoja diaria del repartidor --}}
                <form method="GET" action="{{ route('reparto.hoja') }}"
                    class="bg-superficie border border-miga rounded-lg shadow-sm px-5 py-4 flex items-end gap-3 flex-wrap">
                    <div>
                        <x-input-label for="route_date" value="Hoja de reparto del día" />
                        <input id="route_date" type="date" name="date" value="{{ now()->toDateString() }}"
                            class="mt-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                    </div>
                    <div>
                        <x-input-label for="route_person" value="Repartidor" />
                        <select id="route_person" name="delivery_person"
                            class="mt-1 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                            @foreach($deliveryPeople as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                            <option value="">Sin repartidor</option>
                        </select>
                    </div>
                    <button type="submit"
                        class="px-4 py-2 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga hover:text-corteza transition-colors">
                        Ver hoja
                    </button>
                </form>

                {{-- Cliente --}}
                <form method="GET" action="{{ route('reparto.pedidos.index') }}" class="flex items-center gap-3 flex-wrap">
                    <input type="hidden" name="start" value="{{ $start->toDateString() }}">
                    <label for="customer_picker" class="text-sm text-masa-madre">Cliente</label>
                    <select id="customer_picker" name="customer" onchange="this.form.submit()"
                        class="min-w-64 border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                        @foreach($customerGroups as $groupName => $group)
                            <optgroup label="{{ $groupName }}">
                                @foreach($group as $option)
                                    <option value="{{ $option->id }}" @selected($option->id === $customer->id)>{{ $option->name }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @if($customer->deliveryPerson === null)
                        <span class="text-xs text-masa-madre">Sin repartidor asignado</span>
                    @endif
                </form>

                {{-- Navegador de semana --}}
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-sm text-corteza font-medium">
                        Semana del {{ $start->format('d/m/Y') }} al {{ $start->addDays(6)->format('d/m/Y') }}
                    </p>
                    <div class="flex items-center gap-2">
                        <a href="{{ $weekUrl($start->subWeek()) }}"
                            class="px-3 py-1.5 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">← Anterior</a>
                        <a href="{{ route('reparto.pedidos.index', ['customer' => $customer->id]) }}"
                            class="px-3 py-1.5 border border-miga text-sm rounded-md hover:bg-miga transition-colors {{ $isCurrentWeek ? 'text-horno' : 'text-masa-madre' }}">Esta semana</a>
                        <a href="{{ $weekUrl($start->addWeek()) }}"
                            class="px-3 py-1.5 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">Siguiente →</a>
                    </div>
                </div>

                <div x-data="orderSheetGrid({{ Js::from(['products' => $products, 'lines' => $lines]) }})">

                    @if($errors->any())
                        <div class="mb-3 text-sm text-red-600 space-y-0.5">
                            @foreach($errors->all() as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if(! $hasWeek)
                        <p class="mb-3 text-xs text-masa-madre">Esta semana pasó y no se cargó nada. Si guardás algo, se crea.</p>
                    @endif

                    <form method="POST" action="{{ route('reparto.pedidos.semana.update', $customer) }}" @submit="submitting = true"
                        class="bg-superficie border border-miga rounded-lg shadow-sm overflow-hidden">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="start" value="{{ $start->toDateString() }}">

                        <p x-show="notice" x-cloak x-text="notice" class="px-5 pt-3 text-xs text-amber-700"></p>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-miga text-masa-madre">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium sticky left-0 bg-miga z-10 min-w-44">Artículo</th>
                                        @foreach($days as $i => $day)
                                            <th class="px-2 py-2 text-center font-medium whitespace-nowrap {{ $day->isToday() ? 'text-horno' : '' }}">
                                                {{ $dayNames[$i] }}
                                                <span class="block text-[11px] font-normal">{{ $day->format('d/m') }}</span>
                                            </th>
                                        @endforeach
                                        <th class="px-3 py-2 text-right font-medium">Semana</th>
                                        @if($canEdit)
                                            <th class="w-8"></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody x-ref="rows" class="divide-y divide-miga">
                                    <template x-for="(line, i) in lines" :key="line.product_id">
                                        <tr>
                                            <td class="px-4 py-2 text-corteza sticky left-0 bg-superficie z-10 align-top">
                                                <input type="hidden" :name="`lines[${i}][product_id]`" :value="line.product_id">
                                                <span x-text="line.name"></span>
                                                <span x-show="! line.container" class="text-xs text-masa-madre" x-text="line.unit"></span>
                                                <span x-show="line.container" x-cloak class="block text-xs text-masa-madre" x-text="line.container ? containerLabel(line) : ''"></span>
                                                <span x-show="line.no_destination" x-cloak class="block text-xs text-amber-700 max-w-56 whitespace-normal">No suma en ninguna planilla de Pedidos.</span>
                                                <span x-show="line.warning" x-cloak class="block text-xs text-amber-700 max-w-56 whitespace-normal" x-text="line.warning"></span>
                                            </td>
                                            @foreach($days as $d => $day)
                                                <td class="px-1.5 py-2 align-top {{ $day->isToday() ? 'bg-horno/5' : '' }}">
                                                    <input type="number" min="0" inputmode="decimal"
                                                        :step="line.container ? 0.25 : 'any'"
                                                        x-model="line.q[{{ $d }}]"
                                                        :name="`lines[${i}][quantities][{{ $d }}]`"
                                                        @if(! $canEdit) readonly @endif
                                                        class="w-20 text-right border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno read-only:bg-transparent read-only:border-transparent read-only:shadow-none">
                                                    <span x-show="line.container" x-cloak class="block text-[11px] text-right text-masa-madre font-mono mt-0.5"
                                                        x-text="line.container ? equivalent(line, {{ $d }}) : ''"></span>
                                                </td>
                                            @endforeach
                                            <td class="px-3 py-2 text-right font-mono text-corteza whitespace-nowrap align-top" x-text="rowTotal(line)"></td>
                                            @if($canEdit)
                                                <td class="px-2 py-2 text-right">
                                                    <button type="button" @click="removeLine(i)" tabindex="-1" title="Quitar artículo"
                                                        class="text-masa-madre hover:text-red-500 transition-colors">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    </template>
                                    <template x-if="lines.length === 0">
                                        <tr>
                                            <td colspan="{{ $canEdit ? 10 : 9 }}" class="px-4 py-6 text-center text-masa-madre">
                                                Este cliente todavía no tiene pedidos{{ $canEdit ? '. Agregá el primer artículo abajo.' : '.' }}
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        @if($canEdit)
                            <div class="px-5 py-3 flex items-center justify-between gap-3 flex-wrap border-t border-miga">
                                <select data-searchable x-ref="picker"
                                    @change="addProduct($event.target.value, $event.target)"
                                    class="min-w-56 flex-1 max-w-md border-gray-300 rounded-md shadow-sm text-sm focus:border-horno focus:ring-horno">
                                    <option value="">Agregar artículo…</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                                    @endforeach
                                </select>
                                <div class="flex items-center gap-3">
                                    <span x-show="dirty" x-cloak class="text-xs text-amber-700">Sin guardar</span>
                                    <button type="submit" :disabled="! dirty"
                                        class="px-4 py-1.5 bg-tinta text-white text-sm rounded-md hover:bg-horno transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                        Guardar semana
                                    </button>
                                </div>
                            </div>
                        @endif
                    </form>

                    @if($canEdit)
                        <form method="POST" action="{{ route('reparto.pedidos.semana.recopy', $customer) }}" class="mt-3"
                            onsubmit="return confirm('¿Volver a copiar la semana anterior? Se pierde lo cargado en esta semana.');">
                            @csrf
                            <input type="hidden" name="start" value="{{ $start->toDateString() }}">
                            <button type="submit" class="text-sm text-horno hover:underline">Volver a copiar la semana anterior</button>
                        </form>
                    @endif
                </div>
            @endif

        </div>
    </div>
</x-app-layout>

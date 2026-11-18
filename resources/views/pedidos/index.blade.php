<x-app-layout>
    <x-slot name="title">Pedidos</x-slot>

    @php
        $dayNames = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $weekUrl = fn (\Carbon\CarbonImmutable $monday, ?int $sheetId = null) => route('pedidos.index', array_filter([
            'sheet' => $sheetId ?? $sheet?->id,
            'start' => $monday->toDateString(),
        ]));
        $isCurrentWeek = $start->isSameDay(now()->startOfWeek(\Carbon\Carbon::MONDAY));
    @endphp

    {{-- x-data raíz: sin él Alpine no procesa los @click que abren el modal. --}}
    <div class="py-8 px-6 lg:px-8" x-data>
        <div class="space-y-6">

            <x-list-header title="Pedidos" subtitle="Lo que se va a elaborar cada día de la semana, por planilla.">
                @if($canEdit)
                    <button type="button"
                        @click="$dispatch('open-modal', 'order-sheets-manage')"
                        class="px-4 py-2 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga hover:text-corteza transition-colors">
                        Configurar planillas
                    </button>
                @endif
            </x-list-header>

            @if($errors->has('order_sheet'))
                <p class="text-sm text-red-600">{{ $errors->first('order_sheet') }}</p>
            @endif

            @if($allSheets->isEmpty())
                <x-empty-state>
                    <p>Todavía no armaste ninguna planilla de pedidos.</p>
                    <p class="mt-1">Cada planilla es una grilla de lunes a domingo (por ejemplo: Secos, Diarios, Tortas, Reparto).</p>
                    @if($canEdit)
                        <button type="button"
                            @click="$dispatch('open-modal', 'order-sheets-manage')"
                            class="mt-4 px-4 py-2 bg-tinta text-white text-sm rounded-md hover:bg-horno transition-colors">
                            Crear la primera planilla
                        </button>
                    @endif
                </x-empty-state>
            @elseif($sheet === null)
                <x-empty-state>Todas las planillas están pausadas. Activá alguna desde "Configurar planillas".</x-empty-state>
            @else
                {{-- Pestañas: una por planilla activa --}}
                <div class="flex items-center gap-1 border-b border-miga overflow-x-auto overflow-y-hidden">
                    @foreach($sheets as $tab)
                        <a href="{{ $weekUrl($start, $tab->id) }}"
                            class="px-4 py-2 text-sm font-medium border-b-2 -mb-px whitespace-nowrap transition-colors {{ $tab->id === $sheet->id ? 'border-horno text-horno' : 'border-transparent text-masa-madre hover:text-corteza' }}">
                            {{ $tab->name }}
                        </a>
                    @endforeach
                </div>

                {{-- Navegador de semana --}}
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <p class="text-sm text-corteza font-medium">
                        Semana del {{ $start->format('d/m/Y') }} al {{ $start->addDays(6)->format('d/m/Y') }}
                    </p>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('pedidos.imprimir', ['orderSheet' => $sheet, 'start' => $start->toDateString()]) }}"
                            title="Imprime la última versión guardada"
                            class="px-3 py-1.5 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">Imprimir</a>
                        <a href="{{ $weekUrl($start->subWeek()) }}"
                            class="px-3 py-1.5 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">← Anterior</a>
                        <a href="{{ route('pedidos.index', ['sheet' => $sheet->id]) }}"
                            class="px-3 py-1.5 border border-miga text-sm rounded-md hover:bg-miga transition-colors {{ $isCurrentWeek ? 'text-horno' : 'text-masa-madre' }}">Esta semana</a>
                        <a href="{{ $weekUrl($start->addWeek()) }}"
                            class="px-3 py-1.5 border border-miga text-sm text-masa-madre rounded-md hover:bg-miga transition-colors">Siguiente →</a>
                    </div>
                </div>

                <div x-data="orderSheetGrid({{ Js::from(['products' => $products, 'lines' => $lines]) }})">

                    @if($errors->any() && ! $errors->has('order_sheet'))
                        <div class="mb-3 text-sm text-red-600 space-y-0.5">
                            @foreach($errors->all() as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if(! $hasWeek)
                        <p class="mb-3 text-xs text-masa-madre">Esta semana pasó y no se cargó nada. Si guardás algo, se crea.</p>
                    @endif

                    <form method="POST" action="{{ route('pedidos.semana.update', $sheet) }}" @submit="submitting = true"
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
                                                    <span x-show="clientsOf(line, {{ $d }}) > 0" x-cloak class="block text-[11px] text-right text-horno font-mono mt-0.5"
                                                        x-text="clientsLabel(line, {{ $d }})"></span>
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
                                                Todavía no hay artículos en esta planilla{{ $canEdit ? '. Agregá el primero abajo.' : '.' }}
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
                        <form method="POST" action="{{ route('pedidos.semana.recopy', $sheet) }}" class="mt-3"
                            onsubmit="return confirm('¿Volver a copiar la semana anterior? Se pierde lo cargado en esta semana.');">
                            @csrf
                            <input type="hidden" name="start" value="{{ $start->toDateString() }}">
                            <button type="submit" class="text-sm text-horno hover:underline">Volver a copiar la semana anterior</button>
                        </form>
                    @endif
                </div>
            @endif

        </div>

        @if($canEdit)
            @include('pedidos.modals.sheets')
        @endif
    </div>
</x-app-layout>

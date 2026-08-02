<x-app-layout>
    <x-slot name="title">Inicio</x-slot>

    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');

        $sort    = request('sort', 'name');
        $dir     = request('dir', 'asc');
        $sortUrl = fn (string $col): string => request()->url() . '?' . http_build_query(
            array_merge(request()->except(['sort', 'dir', 'page']), [
                'sort' => $col,
                'dir'  => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
            ])
        ) . '#tabla-recetas';
        $sortIcon = fn (string $col): string => $sort === $col ? ($dir === 'asc' ? '↑' : '↓') : '';

        $donutHasData = array_sum($costDistributionForChart) > 0;
        $gaugeValue   = $avgMarginPct !== null ? round((float) $avgMarginPct, 1) : 0;
    @endphp

    <div class="py-6 px-6 lg:px-8 space-y-5">

        {{-- ═══════════════════════════════════════════════
             GREETING + QUICK ACTIONS
        ════════════════════════════════════════════════ --}}
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-semibold text-corteza leading-tight">
                    {{ $greeting }}, {{ Auth::user()->name }}.
                </h1>
                <p class="text-sm text-masa-madre mt-1">
                    Tenés <strong class="text-corteza font-semibold">{{ $activeRecipeCount }} recetas activas</strong>
                    &nbsp;·&nbsp; Actualizado al {{ now()->format('d/m/Y') }}
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('recipes.index') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-masa-madre border border-miga rounded-lg bg-white hover:bg-miga hover:text-corteza transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nueva receta
                </a>
                <a href="{{ route('purchases.index') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-masa-madre border border-miga rounded-lg bg-white hover:bg-miga hover:text-corteza transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Compra
                </a>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════
             ALERTAS (feed persistido — NotificationService)
        ════════════════════════════════════════════════ --}}
        @if($alerts->isNotEmpty())
        @php
            $alertPill = fn (string $s) => match ($s) {
                'red'   => 'bg-red-50 text-red-700 border-red-200',
                'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                'blue'  => 'bg-blue-50 text-blue-700 border-blue-200',
                default => 'bg-miga text-masa-madre border-miga',
            };
        @endphp
        <div class="bg-white border border-miga rounded-xl p-4 shadow-[0_1px_4px_rgba(61,43,31,.06)]">
            <div class="flex items-center justify-between mb-2.5">
                <div class="text-[9.5px] font-semibold uppercase tracking-widest text-masa-madre/60">
                    Alertas
                </div>
                <a href="{{ route('notifications.index') }}" class="text-[11.5px] text-brown-500 hover:text-corteza transition-colors">
                    Ver todas{{ $alertCount > $alerts->count() ? " ({$alertCount})" : '' }} →
                </a>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($alerts as $alert)
                    <a href="{{ $alert->action_url ?? route('notifications.index') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[12.5px] font-medium border transition-colors hover:opacity-80 {{ $alertPill($alert->severity) }}">
                        <span>{{ $alert->type->icon() }}</span>
                        <span>{{ $alert->title }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ═══════════════════════════════════════════════
             KPI CARDS
        ════════════════════════════════════════════════ --}}
        {{-- Las dos cards de importe pasan a ancho completo en mobile: un monto de
             8 o 9 dígitos no entra en media pantalla ni bajando la tipografía a un
             tamaño legible. El `order` deja a las dos cards cortas compartiendo la
             primera fila para no estirar el dashboard de más. --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

            {{-- Recetas activas --}}
            <div class="order-1 kpi-card bg-white border border-miga rounded-xl p-4 sm:p-5 shadow-[0_1px_4px_rgba(61,43,31,.06)] hover:shadow-[0_4px_16px_rgba(61,43,31,.09)] transition-shadow group">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-brown-100 flex items-center justify-center text-lg shrink-0">📖</div>
                    {{-- La píldora no entra al lado del ícono en una card de media
                         pantalla, y acá no dice nada que no diga el rótulo de abajo. --}}
                    <span class="hidden sm:inline-flex items-center gap-1 text-[11.5px] font-semibold px-2 py-1 rounded-full bg-green-50 text-green-700 whitespace-nowrap">
                        ↑ activas
                    </span>
                </div>
                <div class="text-2xl font-bold text-corteza leading-none mb-1.5 font-mono">
                    {{ $activeRecipeCount }}
                </div>
                <div class="text-sm text-masa-madre">Recetas activas</div>
            </div>

            {{-- Gastos fijos --}}
            <div class="col-span-2 order-3 sm:col-span-1 sm:order-2 kpi-card bg-white border border-miga rounded-xl p-4 sm:p-5 shadow-[0_1px_4px_rgba(61,43,31,.06)] hover:shadow-[0_4px_16px_rgba(61,43,31,.09)] transition-shadow">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-lg shrink-0">💰</div>
                    <span class="inline-flex items-center gap-1 text-[11.5px] font-semibold px-2 py-1 rounded-full bg-miga text-masa-madre whitespace-nowrap">
                        / mes
                    </span>
                </div>
                {{-- Sin centavos: en un total mensual son ruido y son 3 caracteres
                     que a esta tipografía no sobran. El valor exacto va en el title. --}}
                <div class="kpi-figure font-bold text-corteza leading-none mb-1.5 font-mono"
                    title="$ {{ number_format((float) $totalFixedCosts, 2, ',', '.') }} por mes">
                    $&nbsp;{{ number_format((float) $totalFixedCosts, 0, ',', '.') }}
                </div>
                <div class="text-sm text-masa-madre">Gastos fijos</div>
                <div class="text-[11.5px] text-masa-madre/60 mt-0.5">Sueldos, alquiler, servicios</div>
            </div>

            {{-- Overhead / hora --}}
            <div class="col-span-2 order-4 sm:col-span-1 sm:order-3 kpi-card bg-white border border-miga rounded-xl p-4 sm:p-5 shadow-[0_1px_4px_rgba(61,43,31,.06)] hover:shadow-[0_4px_16px_rgba(61,43,31,.09)] transition-shadow">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-lg shrink-0">🏭</div>
                    <span class="inline-flex items-center gap-1 text-[11.5px] font-semibold px-2 py-1 rounded-full bg-miga text-masa-madre whitespace-nowrap">
                        / hora
                    </span>
                </div>
                @if($overheadPerHour !== null)
                    <div class="kpi-figure font-bold text-corteza leading-none mb-1.5 font-mono"
                        title="$ {{ number_format($overheadPerHour, 2, ',', '.') }} por hora">
                        $&nbsp;{{ number_format($overheadPerHour, 0, ',', '.') }}
                    </div>
                @else
                    <div class="text-2xl font-bold text-masa-madre/50 leading-none mb-1.5">—</div>
                @endif
                <div class="text-sm text-masa-madre">Overhead por hora</div>
                @if($productiveHours === 0)
                    <a href="{{ route('business.edit') }}" class="text-[11.5px] text-horno hover:underline mt-0.5 inline-block">
                        Configurar horas →
                    </a>
                @else
                    <div class="text-[11.5px] text-masa-madre/60 mt-0.5">{{ $productiveHours }} h productivas/mes</div>
                @endif
            </div>

            {{-- Utilidad promedio --}}
            <div class="order-2 sm:order-4 kpi-card bg-white border border-miga rounded-xl p-4 sm:p-5 shadow-[0_1px_4px_rgba(61,43,31,.06)] hover:shadow-[0_4px_16px_rgba(61,43,31,.09)] transition-shadow">
                <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center text-lg shrink-0">📊</div>
                    @if($avgMarginPct !== null)
                        <span class="inline-flex items-center gap-1 text-[11.5px] font-semibold px-2 py-1 rounded-full whitespace-nowrap
                            {{ $avgMarginPct >= 38 ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ $avgMarginPct >= 38 ? '↑' : '↓' }} <span class="hidden sm:inline">obj.&nbsp;</span>38%
                        </span>
                    @endif
                </div>
                @if($avgMarginPct !== null)
                    <div class="text-2xl font-bold text-corteza leading-none mb-1.5 font-mono">
                        {{ number_format((float) $avgMarginPct, 1, ',', '.') }}%
                    </div>
                @else
                    <div class="text-2xl font-bold text-masa-madre/50 leading-none mb-1.5">Sin datos</div>
                @endif
                <div class="text-sm text-masa-madre">Utilidad promedio</div>
                <div class="text-[11.5px] text-masa-madre/60 mt-0.5">Margen sobre precio de venta</div>
            </div>

        </div>

        {{-- ═══════════════════════════════════════════════
             MID GRID: Resumen + Gauge
        ════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Resumen operativo --}}
            <div class="lg:col-span-2 bg-white border border-miga rounded-xl shadow-[0_1px_4px_rgba(61,43,31,.06)] overflow-hidden">
                <div class="flex items-center justify-between px-5 pt-5 pb-1">
                    <div>
                        <div class="font-semibold text-sm text-corteza">Resumen operativo</div>
                        <div class="text-[12px] text-masa-madre mt-0.5">Detectado automáticamente</div>
                    </div>
                    @if($lowMarginCount > 0)
                        <span class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                            {{ $lowMarginCount }} alertas
                        </span>
                    @endif
                </div>
                <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @if($lowMarginCount > 0)
                    <div class="flex items-start gap-3 p-3.5 rounded-lg border border-miga hover:bg-harina/60 transition-colors cursor-default">
                        <span class="text-xl leading-none mt-0.5">⚠</span>
                        <p class="text-[13px] text-corteza leading-relaxed">
                            <strong class="font-semibold">{{ $lowMarginCount }} {{ $lowMarginCount === 1 ? 'receta tiene' : 'recetas tienen' }}</strong>
                            margen menor al 20%. Revisá sus costos o precios.
                        </p>
                    </div>
                    @endif

                    @if($bestRecipeRow)
                    <div class="flex items-start gap-3 p-3.5 rounded-lg border border-miga hover:bg-harina/60 transition-colors cursor-default">
                        <span class="text-xl leading-none mt-0.5">🥇</span>
                        <p class="text-[13px] text-corteza leading-relaxed">
                            La más rentable es
                            <strong class="font-semibold">{{ $bestRecipeRow['recipe']->name }}</strong>
                            con {{ number_format((float)$bestRecipeRow['margin_pct'], 0, ',', '.') }}% de margen.
                        </p>
                    </div>
                    @endif

                    @if($avgMarginPct !== null)
                    <div class="flex items-start gap-3 p-3.5 rounded-lg border border-miga hover:bg-harina/60 transition-colors cursor-default">
                        <span class="text-xl leading-none mt-0.5">💵</span>
                        <p class="text-[13px] text-corteza leading-relaxed">
                            La utilidad promedio es
                            <strong class="font-semibold">{{ number_format((float)$avgMarginPct, 1, ',', '.') }}%</strong>
                            — {{ $avgMarginPct >= 38 ? 'por encima' : 'por debajo' }} del objetivo del 38%.
                        </p>
                    </div>
                    @endif

                    @if($productiveHours > 0 && $overheadPerHour !== null)
                    <div class="flex items-start gap-3 p-3.5 rounded-lg border border-miga hover:bg-harina/60 transition-colors cursor-default">
                        <span class="text-xl leading-none mt-0.5">🏭</span>
                        <p class="text-[13px] text-corteza leading-relaxed">
                            Overhead de
                            <strong class="font-semibold">${{ number_format($overheadPerHour, 2, ',', '.') }}/hora</strong>
                            sobre {{ $productiveHours }} horas productivas mensuales.
                        </p>
                    </div>
                    @endif

                    @if($activeRecipeCount > 0 && $packagingCount === 0)
                    <div class="flex items-start gap-3 p-3.5 rounded-lg border border-amber-200 bg-amber-50/50 hover:bg-amber-50 transition-colors cursor-default">
                        <span class="text-xl leading-none mt-0.5">📦</span>
                        <p class="text-[13px] text-amber-800 leading-relaxed">
                            No hay descartables configurados.
                            <a href="{{ route('packaging.index') }}" class="underline hover:no-underline">Cargar envases →</a>
                        </p>
                    </div>
                    @endif

                    <div class="flex items-start gap-3 p-3.5 rounded-lg border border-miga hover:bg-harina/60 transition-colors cursor-default">
                        <span class="text-xl leading-none mt-0.5">📈</span>
                        <p class="text-[13px] text-corteza leading-relaxed">
                            Gastos fijos totales:
                            <strong class="font-semibold font-mono">${{ number_format((float)$totalFixedCosts, 2, ',', '.') }}</strong>
                            por mes.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Gauge rentabilidad --}}
            <div class="bg-white border border-miga rounded-xl shadow-[0_1px_4px_rgba(61,43,31,.06)] flex flex-col">
                <div class="px-5 pt-5 pb-1">
                    <div class="font-semibold text-sm text-corteza">Rentabilidad promedio</div>
                    <div class="text-[12px] text-masa-madre mt-0.5">Objetivo: 38%</div>
                </div>
                <div id="gaugeChart" class="flex-1"></div>
                <div class="px-5 pb-5 space-y-2 border-t border-miga/60 pt-3 mt-1">
                    @if($avgMarginPct !== null)
                    <div class="flex justify-between text-[12.5px]">
                        <span class="text-masa-madre">Promedio general</span>
                        <span class="font-semibold {{ $avgMarginPct >= 38 ? 'text-green-700' : 'text-amber-700' }}">{{ number_format((float)$avgMarginPct, 1, ',', '.') }}%</span>
                    </div>
                    @endif
                    @if($bestRecipeRow)
                    <div class="flex justify-between text-[12.5px]">
                        <span class="text-masa-madre">Máximo registrado</span>
                        <span class="font-semibold text-green-700">{{ number_format((float)$bestRecipeRow['margin_pct'], 1, ',', '.') }}%</span>
                    </div>
                    @endif
                    @if($lowMarginCount > 0)
                    <div class="flex justify-between text-[12.5px]">
                        <span class="text-masa-madre">Bajo margen (&lt;20%)</span>
                        <span class="font-semibold text-red-600">{{ $lowMarginCount }} recetas</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-[12.5px]">
                        <span class="text-masa-madre">Objetivo</span>
                        <span class="font-semibold text-brown-500">38%</span>
                    </div>
                </div>
            </div>

        </div>

        {{-- ═══════════════════════════════════════════════
             CHARTS: Barras + Dona
        ════════════════════════════════════════════════ --}}
        @if(count($topRecipesForChart) > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Top recetas bar chart --}}
            <div class="lg:col-span-2 bg-white border border-miga rounded-xl shadow-[0_1px_4px_rgba(61,43,31,.06)]">
                <div class="flex items-center justify-between px-5 pt-5 pb-1">
                    <div>
                        <div class="font-semibold text-sm text-corteza">Top recetas por rentabilidad</div>
                        <div class="text-[12px] text-masa-madre mt-0.5">Margen % sobre precio de venta</div>
                    </div>
                    <a href="{{ route('recipes.index') }}"
                        class="text-[12.5px] text-brown-500 hover:text-corteza transition-colors flex items-center gap-1">
                        Ver todas
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
                <div id="barChart" class="px-2 pb-2"></div>
            </div>

            {{-- Distribución de costos --}}
            <div class="bg-white border border-miga rounded-xl shadow-[0_1px_4px_rgba(61,43,31,.06)]">
                <div class="px-5 pt-5 pb-1">
                    <div class="font-semibold text-sm text-corteza">Distribución de costos</div>
                    <div class="text-[12px] text-masa-madre mt-0.5">Composición del costo total</div>
                </div>
                @if($donutHasData)
                    <div id="donutChart"></div>
                    <div class="px-5 pb-5 space-y-2">
                        @php
                            $donutLabels = ['Ingredientes', 'Mano de obra', 'Gastos fijos', 'Descartables'];
                            $donutColors = ['#9B6240','#C49272','#DEC4A8','#EFE3D5'];
                        @endphp
                        @foreach($donutLabels as $i => $label)
                        <div class="flex items-center justify-between text-[12.5px]">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-sm shrink-0" style="background:{{ $donutColors[$i] }}"></span>
                                <span class="text-masa-madre">{{ $label }}</span>
                            </div>
                            <span class="font-semibold text-corteza">{{ $costDistributionForChart[$i] }}%</span>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-5 pb-8 pt-4 text-center text-masa-madre text-sm">
                        Sin datos suficientes
                    </div>
                @endif
            </div>

        </div>
        @endif

        {{-- ═══════════════════════════════════════════════
             TABLA DE RECETAS
        ════════════════════════════════════════════════ --}}
        <div id="tabla-recetas" style="scroll-margin-top:1.5rem" class="bg-white border border-miga rounded-xl shadow-[0_1px_4px_rgba(61,43,31,.06)] overflow-hidden">

            {{-- Toolbar --}}
            <div class="px-5 py-4 border-b border-miga flex items-center gap-3 flex-wrap">
                {{-- El fragmento #tabla-recetas hace que, al buscar/ordenar/filtrar/paginar, el
                     navegador recargue directo en esta sección en vez de arriba de todo. --}}
                <form method="GET" action="{{ route('dashboard') }}#tabla-recetas" class="contents" id="searchForm">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="dir" value="{{ request('dir', 'asc') }}">

                    {{-- Buscador --}}
                    <div class="relative flex-1 min-w-48 max-w-xs">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-masa-madre/60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" name="search" value="{{ request('search') }}"
                            placeholder="Buscar receta…"
                            class="w-full pl-9 pr-3 py-2 text-[13px] border border-miga rounded-lg bg-harina focus:bg-white focus:border-brown-300 focus:ring-1 focus:ring-brown-200 outline-none transition-all placeholder-masa-madre/50 text-corteza">
                    </div>

                    {{-- Selector de lista --}}
                    @if($priceLists->count() > 1)
                    <select name="price_list" onchange="document.getElementById('searchForm').submit()"
                        class="py-2 px-3 text-[13px] border border-miga rounded-lg bg-harina text-masa-madre focus:border-brown-300 outline-none transition-colors">
                        @foreach($priceLists as $list)
                            <option value="{{ $list->id }}" @selected($priceList->id === $list->id)>
                                {{ $list->name }}
                            </option>
                        @endforeach
                    </select>
                    @endif

                    {{-- Filtros rápidos de margen --}}
                    <div class="hidden sm:flex items-center gap-1.5">
                        <span class="text-[11px] text-masa-madre/60 font-medium mr-1">Margen:</span>
                        @foreach([
                            ['label' => 'Todos', 'param' => ''],
                            ['label' => '↑ Alta', 'param' => 'high'],
                            ['label' => '↓ Baja', 'param' => 'low'],
                        ] as $filter)
                        <button type="button"
                            data-filter="{{ $filter['param'] }}"
                            class="margin-filter-btn px-3 py-1.5 rounded-full text-[11.5px] font-medium border transition-colors
                                {{ request('margin_filter', '') === $filter['param']
                                    ? 'bg-corteza text-white border-corteza'
                                    : 'bg-harina text-masa-madre border-miga hover:border-brown-300' }}">
                            {{ $filter['label'] }}
                        </button>
                        @endforeach
                        <input type="hidden" name="margin_filter" id="marginFilterInput" value="{{ request('margin_filter', '') }}">
                    </div>

                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-[13px] font-medium text-white bg-corteza rounded-lg hover:bg-horno transition-colors ml-auto sm:ml-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Buscar
                    </button>

                    @if(request('search') || request('margin_filter'))
                    <a href="{{ route('dashboard') }}#tabla-recetas"
                        class="text-[12.5px] text-masa-madre hover:text-corteza hover:underline transition-colors">
                        Limpiar
                    </a>
                    @endif
                </form>
            </div>

            {{-- Table --}}
            @if($recipeRows->total() === 0)
                <div class="py-16 text-center text-masa-madre text-sm">
                    @if(request('search') || request('margin_filter'))
                        No se encontraron recetas con esos filtros.
                    @else
                        Todavía no hay recetas.
                        @can('manage-costs')
                            <a href="{{ route('recipes.index') }}" class="text-horno hover:underline">Crear la primera receta →</a>
                        @endcan
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="bg-harina border-b border-miga">
                                <th class="px-5 py-3 font-semibold text-[11px] uppercase tracking-wider text-masa-madre/70">
                                    <a href="{{ $sortUrl('name') }}" class="hover:text-corteza inline-flex items-center gap-1 transition-colors">
                                        Receta <span class="text-xs">{{ $sortIcon('name') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-semibold text-[11px] uppercase tracking-wider text-masa-madre/70 text-right">
                                    <a href="{{ $sortUrl('yield_quantity') }}" class="hover:text-corteza inline-flex items-center justify-end gap-1 transition-colors">
                                        Rinde <span class="text-xs">{{ $sortIcon('yield_quantity') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-semibold text-[11px] uppercase tracking-wider text-masa-madre/70 text-right">Costo total</th>
                                <th class="px-4 py-3 font-semibold text-[11px] uppercase tracking-wider text-masa-madre/70 text-right">
                                    <a href="{{ $sortUrl('cost_per_unit') }}" class="hover:text-corteza inline-flex items-center justify-end gap-1 transition-colors">
                                        Costo / u <span class="text-xs">{{ $sortIcon('cost_per_unit') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-semibold text-[11px] uppercase tracking-wider text-masa-madre/70 text-right">
                                    <a href="{{ $sortUrl('selling_price') }}" class="hover:text-corteza inline-flex items-center justify-end gap-1 transition-colors">
                                        Precio ({{ $priceList->name }}) / u <span class="text-xs">{{ $sortIcon('selling_price') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 font-semibold text-[11px] uppercase tracking-wider text-masa-madre/70 text-right w-52">
                                    <a href="{{ $sortUrl('margin_pct') }}" class="hover:text-corteza inline-flex items-center justify-end gap-1 transition-colors">
                                        Margen <span class="text-xs">{{ $sortIcon('margin_pct') }}</span>
                                    </a>
                                </th>
                                <th class="px-4 py-3 w-20"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-miga/60">
                            @foreach($recipeRows as $row)
                                @php
                                    $recipe = $row['recipe'];
                                    $pct = $row['margin_pct'];
                                    $initMarginColor = $pct !== null
                                        ? ($pct >= 60 ? 'text-green-600' : ($pct >= 40 ? 'text-amber-600' : ($pct >= 20 ? 'text-orange-600' : 'text-red-500')))
                                        : 'text-masa-madre';
                                    $initPriceInput     = $row['selling_price'] !== null ? number_format($row['selling_price'], 2, '.', '') : '';
                                    $initPriceFormatted = $row['selling_price'] !== null ? number_format($row['selling_price'], 2, ',', '.') : '';
                                    $initMarginFormatted    = $row['margin'] !== null ? number_format($row['margin'], 2, ',', '.') : '';
                                    $initMarginPctFormatted = $pct !== null ? number_format($pct, 1, ',', '.') : '';
                                @endphp
                                <tr
                                    x-data="priceCell({
                                        url: @js($row['product'] ? route('products.prices.update', [$row['product'], $priceList]) : ''),
                                        price: {{ $row['selling_price'] ?? 'null' }},
                                        priceFormatted: '{{ $initPriceFormatted }}',
                                        marginPct: {{ $row['margin_pct'] ?? 'null' }},
                                        marginPctFormatted: '{{ $initMarginPctFormatted }}',
                                        marginColor: '{{ $initMarginColor }}',
                                        policyType: '{{ $row['policy']['type'] }}',
                                        policyValue: {{ $row['policy']['value'] ?? 'null' }},
                                    })"
                                    class="hover:bg-harina/70 transition-colors {{ $recipe->active ? '' : 'opacity-40' }}"
                                >
                                    {{-- Nombre --}}
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('recipes.show', $recipe) }}"
                                                class="font-medium text-corteza hover:text-horno transition-colors">
                                                {{ $recipe->name }}
                                            </a>
                                            <x-semi-badge :is-semi-elaborate="$recipe->is_semi_elaborate" />
                                        </div>
                                    </td>

                                    {{-- Rinde --}}
                                    <td class="px-4 py-3.5 text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-miga text-masa-madre font-mono">
                                            {{ number_format((float)$recipe->yield_quantity, 0, ',', '.') }}&nbsp;{{ $recipe->yield_unit->short() }}
                                        </span>
                                    </td>

                                    {{-- Costo total --}}
                                    <td class="px-4 py-3.5 text-right font-mono text-[13px] text-corteza">
                                        $&nbsp;{{ number_format($row['total_cost'], 2, ',', '.') }}
                                    </td>

                                    {{-- Costo / u --}}
                                    <td class="px-4 py-3.5 text-right font-mono text-[13px] text-corteza">
                                        @if($row['cost_per_unit'] !== null)
                                            $&nbsp;{{ number_format($row['cost_per_unit'], 2, ',', '.') }}
                                        @else
                                            <span class="text-masa-madre/50">—</span>
                                        @endif
                                    </td>

                                    {{-- Precio de venta (editable si la receta tiene artículo elaborado) --}}
                                    <td class="px-4 py-3.5 text-right font-mono text-[13px]">
                                        @can('manage-costs')
                                            @if($row['product'])
                                                <div @click="startEdit($event)"
                                                    class="cursor-pointer text-corteza hover:text-horno transition-colors select-none inline-flex flex-col items-end gap-0.5">
                                                    <span x-show="!saving">
                                                        <span x-show="price !== null" x-text="'$ ' + priceFormatted"></span>
                                                        <span x-show="price === null"
                                                            class="text-[12px] text-masa-madre/50 hover:text-masa-madre">
                                                            Agregar →
                                                        </span>
                                                    </span>
                                                    <span x-show="saving" class="text-[11.5px] text-masa-madre/60">guardando…</span>
                                                    <span x-show="hasPolicy" x-text="policyBadge"
                                                        class="text-[10px] px-1.5 py-0.5 rounded bg-miga text-masa-madre font-sans leading-none"></span>
                                                </div>
                                                <x-price-cell-editor />
                                            @else
                                                {{-- Sin artículo elaborado: el precio se carga en Artículos --}}
                                                <span class="text-[11px] text-masa-madre/50" title="Creá el artículo elaborado para ponerle precio">—</span>
                                            @endif
                                        @else
                                            @if($row['selling_price'] !== null)
                                                <span class="text-corteza">$&nbsp;{{ $initPriceFormatted }}</span>
                                            @else
                                                <span class="text-masa-madre/50">—</span>
                                            @endif
                                        @endcan
                                    </td>

                                    {{-- Margen con barra --}}
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-3">
                                            {{-- Barra de progreso --}}
                                            <div class="flex-1 min-w-[52px]">
                                                <div class="h-1.5 rounded-full bg-miga overflow-hidden">
                                                    <div
                                                        class="h-full rounded-full transition-all duration-500"
                                                        :class="marginPct === null ? 'bg-miga' : (marginPct >= 60 ? 'bg-green-500' : (marginPct >= 40 ? 'bg-amber-500' : (marginPct >= 20 ? 'bg-orange-400' : 'bg-red-500')))"
                                                        :style="'width: ' + Math.min(Math.max(marginPct ?? 0, 0), 100) + '%'">
                                                    </div>
                                                </div>
                                            </div>
                                            {{-- Valor % --}}
                                            <span
                                                x-show="marginPct !== null"
                                                :class="marginColor"
                                                class="font-semibold font-mono text-[13px] min-w-[42px] text-right"
                                                x-text="marginPct !== null ? marginPctFormatted + '%' : ''">
                                            </span>
                                            <span x-show="marginPct === null" class="text-masa-madre/50 text-[13px]">—</span>
                                            {{-- Badge estado --}}
                                            <span
                                                x-show="marginPct !== null"
                                                :class="marginPct === null ? 'bg-miga text-masa-madre' : (marginPct >= 60 ? 'bg-green-100 text-green-700' : (marginPct >= 40 ? 'bg-amber-100 text-amber-700' : (marginPct >= 20 ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-700')))"
                                                class="hidden lg:inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-semibold"
                                                x-text="marginPct === null ? '—' : (marginPct >= 60 ? 'Alta' : (marginPct >= 40 ? 'Media' : (marginPct >= 20 ? 'Regular' : 'Baja')))">
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Acciones --}}
                                    <td class="px-4 py-3.5 text-right">
                                        <a href="{{ route('recipes.show', $recipe) }}"
                                            class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-masa-madre hover:text-corteza hover:bg-miga transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Footer --}}
                <div class="px-5 py-3.5 border-t border-miga flex items-center justify-between bg-harina/40">
                    <span class="text-[12.5px] text-masa-madre">
                        Mostrando {{ $recipeRows->firstItem() }}–{{ $recipeRows->lastItem() }} de {{ $recipeRows->total() }} recetas
                    </span>
                    @if($recipeRows->hasPages())
                        <div class="text-sm">
                            {{ $recipeRows->fragment('tabla-recetas')->links() }}
                        </div>
                    @endif
                </div>
            @endif

        </div>
        {{-- fin tabla --}}

    </div>

    {{-- Datos para los gráficos del dashboard (los renderiza resources/js/dashboard-charts.js) --}}
    @php
        $chartData = [
            'gaugeValue' => $gaugeValue,
            'topRecipes' => $topRecipesForChart,
            'costDistribution' => $costDistributionForChart,
        ];
    @endphp
    <script type="application/json" id="dashboard-chart-data">
        @json($chartData)
    </script>
</x-app-layout>

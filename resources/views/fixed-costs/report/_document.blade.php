{{--
    Contenido del reporte, compartido por pantalla y PDF. Recibe sólo el
    array $report armado por App\Services\FixedCostReport::build() -ni un
    modelo Eloquent- para no arriesgar un lazy load dentro del render de
    dompdf, donde una excepción deja un PDF corrupto en vez de un error
    legible.
--}}
<div class="levado-report">

    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                @if($report['business']['logo'])
                    <img src="{{ $report['business']['logo'] }}" alt="{{ $report['business']['name'] }}" class="logo"><br>
                @endif
                <p class="business-name">{{ $report['business']['razon_social'] ?? $report['business']['name'] }}</p>
                @if($report['business']['cuit'] || $report['business']['condicion_iva'])
                    <p class="business-meta">
                        {{ implode(' · ', array_filter([
                            $report['business']['cuit'] ? 'CUIT '.$report['business']['cuit'] : null,
                            $report['business']['condicion_iva'],
                        ])) }}
                    </p>
                @endif
            </td>
            <td style="width: 40%;">
                @php
                    $fixedFilters = array_filter([
                        $report['meta']['filters']['search'] ? '«'.$report['meta']['filters']['search'].'»' : null,
                        $report['meta']['filters']['status'] === 'active' ? 'sólo activos' : ($report['meta']['filters']['status'] === 'inactive' ? 'sólo inactivos' : null),
                        $report['meta']['filters']['category'] ? 'categoría '.$report['meta']['filters']['category'] : null,
                    ]);
                    $variableFilters = array_filter([
                        $report['meta']['filters']['ve_search'] ? '«'.$report['meta']['filters']['ve_search'].'»' : null,
                        $report['meta']['filters']['ve_category'] ? 'categoría '.$report['meta']['filters']['ve_category'] : null,
                        $report['meta']['filters']['ve_supplier'] ? 'proveedor '.$report['meta']['filters']['ve_supplier'] : null,
                    ]);
                @endphp
                <p class="report-title">Reporte de gastos</p>
                <p class="report-meta">
                    Período: {{ $report['meta']['from'] }} al {{ $report['meta']['to'] }}<br>
                    Emitido: {{ $report['meta']['generated_at'] }}
                    @if($fixedFilters)
                        <br>Filtros gastos fijos: {{ implode(', ', $fixedFilters) }}
                    @endif
                    @if($variableFilters)
                        <br>Filtros gastos variables: {{ implode(', ', $variableFilters) }}
                    @endif
                </p>
            </td>
        </tr>
    </table>

    {{-- Gastos fijos vigentes --}}
    @if(in_array('current', $report['meta']['sections'], true))
        <div class="section">
            <p class="section-title">Gastos fijos vigentes — {{ $report['meta']['snapshot_label'] }}</p>

            @if(empty($report['current']['rows']))
                <div class="empty">No hay gastos fijos registrados para este período.</div>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['current']['rows'] as $row)
                            <tr @class(['inactive-row' => ! $row['active']])>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['category'] ?? '—' }}</td>
                                <td>{{ $row['active'] ? 'Activo' : 'Inactivo' }}</td>
                                <td class="text-right amount">
                                    $ {{ number_format($row['amount'], 2, ',', '.') }}
                                    @if($row['carried'])
                                        <span class="muted">(arrastrado)</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3">Total</td>
                            <td class="text-right amount">$ {{ number_format($report['current']['total'], 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    @endif

    {{-- Histórico mensual comparativo --}}
    @if(in_array('monthly', $report['meta']['sections'], true) && $report['monthly'])
        <div class="section">
            <p class="section-title">Histórico mensual ({{ $report['meta']['months'] }} meses)</p>

            <table class="data">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Variación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['monthly']['rows'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="text-right amount">$ {{ number_format($row['total'], 2, ',', '.') }}</td>
                            <td class="text-right amount @if($row['change_pct'] !== null) {{ $row['change_pct'] > 0 ? 'up' : ($row['change_pct'] < 0 ? 'down' : '') }} @endif">
                                @if($row['change_pct'] !== null)
                                    {{ $row['change_pct'] > 0 ? '+' : '' }}{{ number_format($row['change_pct'], 1, ',', '.') }}%
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="footnote">
                Promedio: $ {{ number_format($report['monthly']['average'], 2, ',', '.') }} ·
                Mínimo: $ {{ number_format($report['monthly']['min'], 2, ',', '.') }} ·
                Máximo: $ {{ number_format($report['monthly']['max'], 2, ',', '.') }}
            </p>
        </div>
    @endif

    {{-- Detalle por gasto --}}
    @if(in_array('details', $report['meta']['sections'], true) && $report['details'])
        <div class="section">
            <p class="section-title">Detalle por gasto</p>

            @if(empty($report['details']['rows']))
                <div class="empty">No hay montos registrados para el detalle por gasto.</div>
            @else
                @foreach($report['details']['rows'] as $detail)
                    <div class="detail-block">
                        <p class="detail-name">{{ $detail['name'] }}</p>
                        <p class="detail-category">{{ $detail['category'] ?? 'Sin categoría' }}</p>
                        @if(empty($detail['timeline']))
                            <p class="footnote">Sin montos registrados en la ventana del reporte.</p>
                        @else
                            <table class="data">
                                <thead>
                                    <tr>
                                        <th>Mes</th>
                                        <th class="text-right">Monto</th>
                                        <th class="text-right">Variación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($detail['timeline'] as $point)
                                        <tr>
                                            <td>{{ $point['label'] }}</td>
                                            <td class="text-right amount">$ {{ number_format($point['amount'], 2, ',', '.') }}</td>
                                            <td class="text-right amount @if($point['change_pct'] !== null) {{ $point['change_pct'] > 0 ? 'up' : ($point['change_pct'] < 0 ? 'down' : '') }} @endif">
                                                @if($point['change_pct'] !== null)
                                                    {{ $point['change_pct'] > 0 ? '+' : '' }}{{ number_format($point['change_pct'], 1, ',', '.') }}%
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach

                @if($report['details']['truncated'])
                    <p class="footnote">
                        Mostrando los {{ count($report['details']['rows']) }} gastos de mayor monto
                        (de {{ $report['details']['total_count'] }} en total).
                    </p>
                @endif
            @endif
        </div>
    @endif

    {{-- Gastos variables del período --}}
    @if(in_array('variable', $report['meta']['sections'], true) && $report['variable'])
        <div class="section">
            <p class="section-title">Gastos variables — {{ $report['meta']['from'] }} al {{ $report['meta']['to'] }}</p>

            @if(empty($report['variable']['rows']))
                <div class="empty">No hay gastos variables registrados en este período.</div>
            @else
                <table class="data">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Proveedor</th>
                            <th>Descripción</th>
                            <th class="text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['variable']['rows'] as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['category'] ?? '—' }}</td>
                                <td>{{ $row['supplier'] ?? '—' }}</td>
                                <td>{{ $row['description'] ?? '—' }}</td>
                                <td class="text-right amount">$ {{ number_format($row['amount'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5">Total</td>
                            <td class="text-right amount">$ {{ number_format($report['variable']['total'], 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    @endif

    {{-- Totales --}}
    <div class="section">
        <table class="totals-table">
            <tr>
                <td>Total gastos fijos</td>
                <td class="text-right amount">$ {{ number_format($report['totals']['fixed'], 2, ',', '.') }}</td>
            </tr>
            @if(in_array('variable', $report['meta']['sections'], true))
                <tr>
                    <td>Total gastos variables</td>
                    <td class="text-right amount">$ {{ number_format($report['totals']['variable'], 2, ',', '.') }}</td>
                </tr>
            @endif
            <tr class="grand">
                <td>Total del período</td>
                <td class="text-right amount">$ {{ number_format($report['totals']['grand'], 2, ',', '.') }}</td>
            </tr>
        </table>
        @if($report['totals']['overhead_per_hour'] !== null)
            <p class="footnote">
                Overhead por hora productiva: $ {{ number_format($report['totals']['overhead_per_hour'], 2, ',', '.') }}
                ({{ $report['totals']['productive_hours'] }} hs. productivas/mes)
            </p>
        @endif
    </div>

</div>

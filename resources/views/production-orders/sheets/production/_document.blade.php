{{--
    Contenido de la planilla de producción, compartido por pantalla y PDF.
    Recibe sólo el array $sheet armado por App\Services\ProductionOrderSheets::production()
    -ni un modelo Eloquent- para no arriesgar un lazy load dentro del render
    de dompdf, donde una excepción deja un PDF corrupto en vez de un error
    legible.
--}}
<div class="levado-report">

    <table class="header-table">
        <tr>
            <td style="width: 35%;">
                @if($sheet['business']['logo'])
                    <img src="{{ $sheet['business']['logo'] }}" alt="{{ $sheet['business']['name'] }}" class="logo"><br>
                @endif
                <p class="business-name">{{ $sheet['business']['razon_social'] ?? $sheet['business']['name'] }}</p>
            </td>
            <td style="width: 65%;">
                <p class="report-title">Planilla de producción — {{ $sheet['meta']['number'] }}</p>
                <p class="sheet-meta">
                    Tipo: {{ $sheet['meta']['type'] }} · Fecha: {{ $sheet['meta']['date'] ?? 'Sin fecha' }} · Emitido: {{ $sheet['meta']['generated_at'] }}
                </p>
            </td>
        </tr>
    </table>

    <div class="section">
        <p class="section-title">Productos a producir</p>

        @if(empty($sheet['products']))
            <div class="empty">Esta orden no tiene productos.</div>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-right">Cantidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sheet['products'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-right amount">{{ number_format($row['quantity'], 2, ',', '.') }} {{ $row['unit'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section">
        <p class="section-title">Insumos necesarios</p>

        @if(empty($sheet['ingredients']))
            <div class="empty">No hay insumos para calcular.</div>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Insumo</th>
                        <th class="text-right">Necesario</th>
                        <th class="text-right">Disponible</th>
                        <th class="text-right">Falta</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sheet['ingredients'] as $row)
                        <tr @class(['shortfall-row' => $row['shortfall'] > 0])>
                            <td>{{ $row['name'] }}</td>
                            <td class="text-right amount">{{ number_format($row['quantity'], 2, ',', '.') }} {{ $row['unit'] }}</td>
                            <td class="text-right amount">{{ number_format($row['available'], 2, ',', '.') }} {{ $row['unit'] }}</td>
                            <td class="text-right amount">
                                @if($row['shortfall'] > 0)
                                    {{ number_format($row['shortfall'], 2, ',', '.') }} {{ $row['unit'] }}
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

</div>

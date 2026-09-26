{{--
    Contenido de la planilla de reparto, compartido por pantalla y PDF.
    Recibe sólo el array $sheet armado por App\Services\ProductionOrderSheets::delivery()
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
                <p class="report-title">Planilla de reparto — {{ $sheet['meta']['number'] }}</p>
                <p class="sheet-meta">
                    Tipo: {{ $sheet['meta']['type'] }} · Fecha: {{ $sheet['meta']['date'] ?? 'Sin fecha' }} · Emitido: {{ $sheet['meta']['generated_at'] }}
                </p>
            </td>
        </tr>
    </table>

    @if(empty($sheet['groups']))
        <div class="empty">Esta orden no tiene pedidos.</div>
    @else
        @foreach($sheet['groups'] as $group)
            <div class="group-block">
                <p class="group-title">{{ $group['title'] }}</p>
                @if($group['subtitle'])
                    <p class="group-subtitle">{{ $group['subtitle'] }}</p>
                @endif

                @foreach($group['requests'] as $request)
                    <div class="request-block">
                        <div class="request-line">
                            <span class="request-number">{{ $request['number'] }}</span>
                            - {{ $request['destination'] }}
                            @if($request['address'])
                                - {{ $request['address'] }}@if($request['city']), {{ $request['city'] }}@endif
                            @endif
                        </div>
                        @if($request['notes'])
                            <div class="request-note">{{ $request['notes'] }}</div>
                        @endif

                        @if(empty($request['lines']))
                            <p class="footnote">Sin artículos.</p>
                        @else
                            <table class="data">
                                <tbody>
                                    @foreach($request['lines'] as $line)
                                        <tr>
                                            <td>{{ $line['product'] }}</td>
                                            <td class="text-right amount">{{ number_format($line['quantity'], 2, ',', '.') }} {{ $line['unit'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach

                @if(count($group['requests']) > 1)
                    <table class="data delivery-totals">
                        <thead>
                            <tr>
                                <th>Total a llevar</th>
                                <th class="text-right">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group['totals'] as $total)
                                <tr>
                                    <td>{{ $total['product'] }}</td>
                                    <td class="text-right amount">{{ number_format($total['quantity'], 2, ',', '.') }} {{ $total['unit'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
    @endif

</div>

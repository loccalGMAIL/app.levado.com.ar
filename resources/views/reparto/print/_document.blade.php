{{--
    Hoja diaria del repartidor imprimible, compartida por pantalla y PDF. Recibe
    sólo el array $sheet armado por App\Services\DeliveryRouteSheet::for() -ni un
    modelo Eloquent- para no arriesgar un lazy load dentro del render de dompdf.
    Sin la clase `grid` en la tabla: Tailwind la vuelve display:grid.
--}}
<style>
    .levado-report table.route-grid th,
    .levado-report table.route-grid td { padding: 4px 5px; font-size: 10px; vertical-align: top; }
    .levado-report table.route-grid th.product { text-align: center; }
    .levado-report table.route-grid td.cell { text-align: center; border-left: 1px solid #F2EAD8; }
    .levado-report table.route-grid .detail { display: block; font-size: 8px; font-weight: 400; color: #6B5B45; }
    .levado-report table.route-grid .address { display: block; font-size: 8px; color: #6B5B45; }
    .levado-report table.route-grid td.number { width: 4%; text-align: center; color: #6B5B45; }
    .levado-report table.route-grid tr.load td { font-weight: 700; border-top: 2px solid #6B5B45; }
    .levado-report table.route-grid tr { page-break-inside: avoid; }
</style>

<div class="levado-report">

    <table class="header-table">
        <tr>
            <td style="width: 35%;">
                @if($sheet['business']['logo'])
                    <img src="{{ $sheet['business']['logo'] }}" alt="{{ $sheet['business']['name'] }}" class="logo"><br>
                @endif
                <p class="business-name">{{ $sheet['business']['razon_social'] ?? $sheet['business']['name'] }}</p>
                @if($sheet['business']['cuit'])
                    <p class="business-meta">CUIT {{ $sheet['business']['cuit'] }}</p>
                @endif
            </td>
            <td style="width: 65%;">
                <p class="report-title" style="font-size: 15px;">{{ $sheet['title'] }}</p>
                <p class="report-meta">{{ $sheet['period'] }} · Emitido: {{ $sheet['generated_at'] }}</p>
            </td>
        </tr>
    </table>

    @if(empty($sheet['rows']))
        <div class="empty">Sin pedidos de clientes para este día.</div>
    @else
        <table class="data route-grid">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Cliente</th>
                    @foreach($sheet['products'] as $product)
                        <th class="product">{{ $product['name'] }}<span class="detail">{{ $product['detail'] }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($sheet['rows'] as $row)
                    <tr>
                        <td class="number">{{ $row['number'] }}</td>
                        <td>
                            {{ $row['name'] }}
                            @if($row['address'] !== '')
                                <span class="address">{{ $row['address'] }}</span>
                            @endif
                        </td>
                        @foreach($sheet['products'] as $product)
                            <td class="cell">{{ $row['cells'][$product['id']] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr class="load">
                    <td></td>
                    <td>CARGAR</td>
                    @foreach($sheet['products'] as $product)
                        <td class="cell">{{ $sheet['load'][$product['id']] ?? '' }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    @endif

</div>

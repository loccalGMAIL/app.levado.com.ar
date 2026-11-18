{{--
    Planilla de pedidos imprimible, compartida por pantalla y PDF. Recibe sólo
    el array $sheet armado por App\Services\OrderSheetPrintSheet::for() -ni un
    modelo Eloquent- para no arriesgar un lazy load dentro del render de dompdf.
--}}
<style>
    .levado-report table.orders-grid th,
    .levado-report table.orders-grid td { padding: 4px 5px; font-size: 10px; vertical-align: top; }
    .levado-report table.orders-grid th.day { text-align: center; width: 9%; }
    .levado-report table.orders-grid td.cell { text-align: center; border-left: 1px solid #F2EAD8; }
    .levado-report table.orders-grid .equiv { font-size: 8px; color: #6B5B45; white-space: nowrap; }
    .levado-report table.orders-grid .detail { font-size: 8px; color: #6B5B45; margin-left: 3px; }
    .levado-report table.orders-grid .warn { display: block; font-size: 8px; color: #B91C1C; }
    .levado-report table.orders-grid td.total { text-align: right; font-weight: 700; border-left: 1px solid #F2EAD8; }
    .levado-report table.orders-grid tr { page-break-inside: avoid; }
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
                <p class="report-title" style="font-size: 15px;">Pedidos — {{ $sheet['title'] }}</p>
                <p class="report-meta">{{ $sheet['period'] }} · Emitido: {{ $sheet['generated_at'] }}</p>
            </td>
        </tr>
    </table>

    @if(empty($sheet['rows']))
        <div class="empty">Sin pedidos cargados para esta semana.</div>
    @else
        <table class="data orders-grid">
            <thead>
                <tr>
                    <th>Artículo</th>
                    @foreach($sheet['days'] as $day)
                        <th class="day">{{ $day['name'] }}<br><span style="font-weight: 400;">{{ $day['date'] }}</span></th>
                    @endforeach
                    <th style="width: 13%; text-align: right;">Semana</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sheet['rows'] as $row)
                    <tr>
                        <td>
                            {{ $row['name'] }}
                            <span class="detail">{{ $row['detail'] }}</span>
                            @if($row['warning'])
                                <span class="warn">{{ $row['warning'] }}</span>
                            @endif
                        </td>
                        @foreach($row['cells'] as $cell)
                            <td class="cell">
                                {{ $cell['qty'] }}
                                @if($cell['equivalent'] !== '')
                                    <span class="equiv">({{ $cell['equivalent'] }})</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="total">{{ $row['total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</div>

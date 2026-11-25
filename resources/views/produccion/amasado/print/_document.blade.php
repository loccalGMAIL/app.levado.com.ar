{{--
    Planilla de amasado imprimible, compartida por pantalla y PDF. Recibe el
    array $sheet armado por DoughPlanController (sin modelos Eloquent, para no
    arriesgar un lazy load dentro del render de dompdf).
--}}
@php
    $qty = fn (float $value) => \App\Services\QuantityFormatter::format($value);
    $plan = $sheet['plan'];
    $sections = [
        ['rows' => $plan['chamber'], 'title' => 'Va a cámara (para mañana '.$plan['chamber_date']->format('d/m').')', 'chamber' => true],
        ['rows' => $plan['direct'], 'title' => 'Directo (para hoy '.$plan['date']->format('d/m').')', 'chamber' => false],
    ];
@endphp
<style>
    .levado-report table.dough th,
    .levado-report table.dough td { padding: 5px 6px; font-size: 11px; vertical-align: top; }
    .levado-report table.dough td.num { text-align: right; white-space: nowrap; }
    .levado-report table.dough td.make { text-align: right; font-weight: 700; white-space: nowrap; }
    .levado-report table.dough .sub { display: block; font-size: 9px; font-weight: 400; color: #6B5B45; }
    .levado-report table.dough td.check { width: 14%; border-left: 1px solid #F2EAD8; }
    .levado-report h3.section { font-size: 12px; margin: 14px 0 4px; color: #3B2A1A; }
    .levado-report table.dough tr { page-break-inside: avoid; }
</style>

<div class="levado-report">

    <table class="header-table">
        <tr>
            <td style="width: 40%;">
                @if($sheet['business']['logo'])
                    <img src="{{ $sheet['business']['logo'] }}" alt="{{ $sheet['business']['name'] }}" class="logo"><br>
                @endif
                <p class="business-name">{{ $sheet['business']['razon_social'] ?? $sheet['business']['name'] }}</p>
            </td>
            <td style="width: 60%;">
                <p class="report-title" style="font-size: 15px;">Amasado — {{ $plan['date']->format('d/m/Y') }}</p>
                <p class="report-meta">Emitido: {{ $sheet['generated_at'] }}</p>
            </td>
        </tr>
    </table>

    @foreach($sections as $section)
        <h3 class="section">{{ $section['title'] }}</h3>
        @if(empty($section['rows']))
            <div class="empty">Nada pedido.</div>
        @else
            <table class="data dough">
                <thead>
                    <tr>
                        <th>Artículo</th>
                        <th style="text-align: right;">Pedido</th>
                        @if($section['chamber'])
                            <th style="text-align: right;">En cámara</th>
                        @endif
                        <th style="text-align: right;">A amasar</th>
                        <th>Real</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['rows'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td class="num">{{ $qty($row['ordered']) }} {{ $row['unit'] }}</td>
                            @if($section['chamber'])
                                <td class="num">{{ $qty($row['in_chamber']) }}</td>
                            @endif
                            <td class="make">
                                @if($row['containers'] !== null)
                                    {{ $qty($row['containers']) }} {{ $row['container']['short'] }}
                                    <span class="sub">{{ $qty($row['to_make']) }} {{ $row['unit'] }}</span>
                                @else
                                    {{ $qty($row['to_make']) }} {{ $row['unit'] }}
                                @endif
                            </td>
                            <td class="check"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <h3 class="section">Masas a preparar</h3>
    @if(empty($plan['masses']) && empty($plan['without_mass']))
        <div class="empty">Nada para amasar.</div>
    @else
        <table class="data dough">
            <thead>
                <tr>
                    <th>Masa</th>
                    <th>Para</th>
                    <th style="text-align: right;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach($plan['masses'] as $mass)
                    <tr>
                        <td>{{ $mass['name'] }}</td>
                        <td>{{ implode(', ', $mass['articles']) }}</td>
                        <td class="make">{{ $qty($mass['quantity']) }} {{ $mass['unit'] }}</td>
                    </tr>
                @endforeach
                @if(! empty($plan['without_mass']))
                    <tr>
                        <td>Sin masa común</td>
                        <td colspan="2">{{ implode(', ', $plan['without_mass']) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

</div>
